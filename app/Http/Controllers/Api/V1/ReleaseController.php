<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CompleteReleaseRequest;
use App\Http\Requests\ReleaseRequest;
use App\Http\Resources\V1\ReleaseResource;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Models\Release;
use App\Services\OverlapChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Release planning.
 *
 * The one behaviour worth stating plainly: a same-team scheduling overlap is a
 * *warning*, never a rejection. The web app saves and warns, so the API does
 * too — it returns 201/200 with the conflicting releases attached beside
 * `data`. A client that ignores the warning still gets a saved release, which
 * is the point: the planner, not the tool, decides whether a double-booking is
 * acceptable.
 */
class ReleaseController extends ApiController
{
    public function __construct(private readonly OverlapChecker $overlap) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->input('status'); // active | completed | (all)

        $query = Release::query()
            ->with(['project', 'team', 'completedBy'])
            ->withCount(['tasks', 'documents', 'comments', 'offDays', 'members'])
            ->when($status === 'active', fn ($q) => $q->ongoing())
            ->when($status === 'completed', fn ($q) => $q->completed())
            ->when($this->filterId($request, 'project_id'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($this->filterId($request, 'team_id'), fn ($q, $id) => $q->where('team_id', $id))
            ->when($this->filterId($request, 'year'), fn ($q, $year) => $q->where('year', $year))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('year', 'desc')
            ->orderBy('quarter', 'desc')
            ->orderBy('start_date', 'desc');

        return $this->paginate($request, $query, ReleaseResource::class);
    }

    /**
     * A release with everything its detail screen needs. Meeting notes are
     * scoped to the viewer, so an attendees-only note never rides along in a
     * payload for someone who may not read it.
     */
    public function show(Request $request, Release $release): JsonResponse
    {
        $release->load([
            'project', 'team', 'phases', 'completedBy', 'members',
            'documents.uploader', 'offDays', 'comments.user',
            'rootTasks.assignee', 'rootTasks.subtasks.assignee',
        ])->loadCount(['tasks', 'documents', 'comments', 'offDays', 'members']);

        $release->setRelation('meetingNotes', $release->meetingNotes()
            ->with('author')->visibleTo($request->user())->get());

        $release->setRelation('quickLinks', $release->quickLinks()
            ->with('author')->visibleTo($request->user())->get());

        return $this->ok(
            (new ReleaseResource($release))->additional([
                'conflicts' => ReleaseSummaryResource::collection($this->conflictsFor($release))->resolve(),
            ])
        );
    }

    public function store(ReleaseRequest $request): JsonResponse
    {
        $release = DB::transaction(function () use ($request) {
            $release = Release::create($request->safe()->only([
                'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
            ]));

            $this->syncPhases($release, $request->input('phases', []));
            $this->syncOffDays($release, $request->input('off_days', []));
            $release->members()->sync($request->input('members', []));

            return $release;
        });

        return $this->created(
            $this->hydrate($release)->additional($this->overlapWarning($release)),
            "Release “{$release->name}” created."
        );
    }

    public function update(ReleaseRequest $request, Release $release): JsonResponse
    {
        DB::transaction(function () use ($request, $release) {
            $release->update($request->safe()->only([
                'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
            ]));

            $this->syncPhases($release, $request->input('phases', []));
            $this->syncOffDays($release, $request->input('off_days', []));
            $release->members()->sync($request->input('members', []));
        });

        $release->refresh();

        return $this->ok(
            $this->hydrate($release)->additional($this->overlapWarning($release)),
            "Release “{$release->name}” updated."
        );
    }

    public function destroy(Release $release): JsonResponse
    {
        $name = $release->name;
        $release->delete(); // cascades phases/documents; the model event removes stored files

        return $this->message("Release “{$name}” deleted.");
    }

    public function complete(CompleteReleaseRequest $request, Release $release): JsonResponse
    {
        $release->update([
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
            'completion_notes' => $request->input('completion_notes'),
        ]);

        return $this->ok($this->hydrate($release), "Release “{$release->name}” marked complete.");
    }

    public function reopen(Release $release): JsonResponse
    {
        $release->update(['completed_at' => null, 'completed_by' => null]);

        return $this->ok($this->hydrate($release), "Release “{$release->name}” reopened.");
    }

    /** The other releases this one double-books its team against. */
    public function conflicts(Release $release): JsonResponse
    {
        return $this->ok(ReleaseSummaryResource::collection($this->conflictsFor($release)));
    }

    /** Load the relation set every single-release response returns. */
    private function hydrate(Release $release): ReleaseResource
    {
        return new ReleaseResource(
            $release->load(['project', 'team', 'phases', 'members', 'offDays', 'completedBy'])
                ->loadCount(['tasks', 'documents', 'comments', 'offDays', 'members'])
        );
    }

    /** @return Collection<int, Release> */
    private function conflictsFor(Release $release)
    {
        return $this->overlap->conflictsFor(
            $release->team_id,
            $release->start_date->toDateString(),
            $release->end_date->toDateString(),
            $release->id
        );
    }

    /**
     * The overlap payload attached beside `data`, or an empty array when the
     * team is free — so the `warning` key is simply absent rather than null.
     *
     * @return array<string, mixed>
     */
    private function overlapWarning(Release $release): array
    {
        $conflicts = $this->conflictsFor($release);

        if ($conflicts->isEmpty()) {
            return [];
        }

        $list = $conflicts->map(fn (Release $c) => sprintf(
            '“%s” (%s – %s)',
            $c->name,
            $c->start_date->format('M j'),
            $c->end_date->format('M j, Y')
        ))->implode('; ');

        return [
            'warning' => [
                'type' => 'team_overlap',
                'message' => sprintf(
                    'Heads up: team %s is already booked during this window by %s. The release was saved anyway.',
                    $release->team->name,
                    $list
                ),
                'conflicts' => ReleaseSummaryResource::collection($conflicts)->resolve(),
            ],
        ];
    }

    /** Recreate the four canonical phases in order from the submitted windows. */
    private function syncPhases(Release $release, array $phases): void
    {
        $release->phases()->delete();

        $position = 0;
        foreach (array_keys(Release::PHASES) as $key) {
            $release->phases()->create([
                'phase' => $key,
                'position' => $position++,
                'start_date' => $phases[$key]['start'] ?? $release->start_date,
                'end_date' => $phases[$key]['end'] ?? $release->end_date,
            ]);
        }
    }

    /**
     * Reconcile off-days against those submitted, keyed by date so unchanged
     * rows are left alone and the activity log stays quiet.
     *
     * @param  array<int, array{date?: string, reason?: string}>  $offDays
     */
    private function syncOffDays(Release $release, array $offDays): void
    {
        $submitted = collect($offDays)
            ->filter(fn ($o) => filled($o['date'] ?? null))
            ->keyBy(fn ($o) => Carbon::parse($o['date'])->toDateString());

        $existing = $release->offDays()->get()->keyBy(fn ($o) => $o->date->toDateString());

        foreach ($existing as $date => $offDay) {
            if (! $submitted->has($date)) {
                $offDay->delete();
            }
        }

        foreach ($submitted as $date => $data) {
            $reason = $data['reason'] ?? null;

            if ($existing->has($date)) {
                $offDay = $existing[$date];
                if ($offDay->reason !== $reason) {
                    $offDay->update(['reason' => $reason]);
                }

                continue;
            }

            $release->offDays()->create(['date' => $date, 'reason' => $reason]);
        }
    }
}
