<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseRequest;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\OverlapChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    public function __construct(private readonly OverlapChecker $overlap) {}

    public function index(Request $request): View
    {
        $status = $request->input('status'); // active | completed | (all)
        $projectId = $request->filled('project_id') ? (int) $request->integer('project_id') : null;
        $teamId = $request->filled('team_id') ? (int) $request->integer('team_id') : null;
        $year = $request->filled('year') ? (int) $request->integer('year') : null;

        $releases = Release::query()
            ->with(['project', 'team', 'completedBy'])
            ->when($status === 'active', fn ($q) => $q->ongoing())
            ->when($status === 'completed', fn ($q) => $q->completed())
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when($year, fn ($q) => $q->where('year', $year))
            ->orderBy('year', 'desc')->orderBy('quarter', 'desc')->orderBy('start_date', 'desc')
            ->get();

        return view('releases.index', [
            'releases' => $releases,
            'projects' => Project::orderBy('name')->get(),
            'teams' => Team::orderBy('name')->get(),
            'years' => Release::query()->select('year')->distinct()->orderBy('year', 'desc')->pluck('year'),
            'filters' => compact('status', 'projectId', 'teamId', 'year'),
        ]);
    }

    public function complete(Request $request, Release $release): RedirectResponse
    {
        $data = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $release->update([
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
            'completion_notes' => $data['completion_notes'] ?? null,
        ]);

        return back()->with('success', "Release “{$release->name}” marked complete.");
    }

    public function reopen(Release $release): RedirectResponse
    {
        $release->update(['completed_at' => null, 'completed_by' => null]);

        return back()->with('success', "Release “{$release->name}” reopened.");
    }

    public function create(): View
    {
        return view('releases.create', [
            'release' => new Release([
                'year' => (int) now()->year,
                'quarter' => (int) ceil(now()->month / 3),
            ]),
            'projects' => Project::active()->orderBy('name')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
            'teamMembers' => $this->teamMembersMap(),
            'memberValues' => [],
            'phaseValues' => [],
            'offDayValues' => [],
        ]);
    }

    public function store(ReleaseRequest $request): RedirectResponse
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

        return redirect()->route('releases.show', $release)
            ->with('success', "Release “{$release->name}” created.")
            ->with($this->overlapSession($release));
    }

    public function show(Release $release): View
    {
        $release->load([
            'project', 'team', 'phases', 'documents.uploader',
            'rootTasks.subtasks.assignee', 'rootTasks.assignee', 'rootTasks.comments',
            'offDays', 'comments.user', 'members',
        ]);

        // Meeting notes the viewer may see (attendees-only ones are filtered out).
        $release->setRelation('meetingNotes', $release->meetingNotes()
            ->with('author')->visibleTo(request()->user())->get());

        $conflicts = $this->overlap->conflictsFor(
            $release->team_id,
            $release->start_date->toDateString(),
            $release->end_date->toDateString(),
            $release->id
        );

        $history = Activity::where('release_id', $release->id)
            ->with('causer')
            ->latest()
            ->limit(40)
            ->get();

        // Assignees are team-wise: the owning team's active members, plus any
        // current assignees who have since left the team.
        $users = $release->team->members()->active()->orderBy('name')->get()
            ->concat($release->rootTasks->map->assignee->filter())
            ->concat($release->rootTasks->flatMap(fn ($t) => $t->subtasks->map->assignee)->filter())
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('releases.show', [
            'release' => $release,
            'conflicts' => $conflicts,
            'history' => $history,
            'users' => $users,
            'releaseLinks' => $release->quickLinks()->with('author')
                ->visibleTo(request()->user())->get(),
        ]);
    }

    public function edit(Release $release): View
    {
        $release->load(['phases', 'members']);

        return view('releases.edit', [
            'release' => $release,
            'projects' => Project::active()->orderBy('name')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
            'teamMembers' => $this->teamMembersMap(),
            'memberValues' => $release->members->pluck('id')->all(),
            'phaseValues' => $release->phases->keyBy('phase'),
            'offDayValues' => $release->offDays()->orderBy('date')->get()
                ->map(fn ($o) => ['date' => $o->date->toDateString(), 'reason' => $o->reason])->all(),
        ]);
    }

    public function update(ReleaseRequest $request, Release $release): RedirectResponse
    {
        DB::transaction(function () use ($request, $release) {
            $release->update($request->safe()->only([
                'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
            ]));
            $this->syncPhases($release, $request->input('phases', []));
            $this->syncOffDays($release, $request->input('off_days', []));
            $release->members()->sync($request->input('members', []));
        });

        return redirect()->route('releases.show', $release)
            ->with('success', "Release “{$release->name}” updated.")
            ->with($this->overlapSession($release->refresh()));
    }

    public function destroy(Release $release): RedirectResponse
    {
        $name = $release->name;
        $release->delete(); // cascades to phases + documents; model event removes files

        return redirect()->route('dashboard')
            ->with('success', "Release “{$name}” deleted.");
    }

    /**
     * Active teams mapped to their active members, for the release form's
     * dependent member picker: [teamId => [['id','name','role'], ...]].
     *
     * @return array<int, array<int, array{id: int, name: string, role: string}>>
     */
    private function teamMembersMap(): array
    {
        return Team::active()
            ->with(['members' => fn ($q) => $q->active()->orderBy('name')])
            ->get()
            ->mapWithKeys(fn (Team $team) => [
                $team->id => $team->members->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'role' => $u->roleLabel(),
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Recreate the four canonical phases in order from the submitted date ranges.
     */
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
     * Reconcile the release's off-days with those submitted on the form, keyed by
     * date so unchanged rows are left alone (keeps the activity log quiet).
     *
     * @param  array<int, array{date?: string, reason?: string}>  $offDays
     */
    private function syncOffDays(Release $release, array $offDays): void
    {
        $submitted = collect($offDays)
            ->filter(fn ($o) => filled($o['date'] ?? null))
            ->keyBy(fn ($o) => Carbon::parse($o['date'])->toDateString());

        $existing = $release->offDays()->get()->keyBy(fn ($o) => $o->date->toDateString());

        // Remove off-days no longer present.
        foreach ($existing as $date => $offDay) {
            if (! $submitted->has($date)) {
                $offDay->delete();
            }
        }

        // Add new ones / update reasons on existing ones.
        foreach ($submitted as $date => $data) {
            $reason = $data['reason'] ?? null;
            if ($existing->has($date)) {
                $offDay = $existing[$date];
                if ($offDay->reason !== $reason) {
                    $offDay->update(['reason' => $reason]);
                }
            } else {
                $release->offDays()->create(['date' => $date, 'reason' => $reason]);
            }
        }
    }

    /**
     * Flash payload for the overlap warning: an array with the warning message,
     * or an empty array when there is no conflict (so the key stays absent).
     *
     * @return array<string, string>
     */
    private function overlapSession(Release $release): array
    {
        $warning = $this->overlapWarning($release);

        return $warning ? ['overlap_warning' => $warning] : [];
    }

    /**
     * Build a human warning if this release overlaps other releases for its team.
     * Returns null when there is no conflict.
     */
    private function overlapWarning(Release $release): ?string
    {
        $conflicts = $this->overlap->conflictsFor(
            $release->team_id,
            $release->start_date->toDateString(),
            $release->end_date->toDateString(),
            $release->id
        );

        if ($conflicts->isEmpty()) {
            return null;
        }

        $list = $conflicts->map(function (Release $c) {
            return sprintf(
                '“%s” (%s – %s)',
                $c->name,
                $c->start_date->format('M j'),
                $c->end_date->format('M j, Y')
            );
        })->implode('; ');

        return sprintf(
            'Heads up: team %s is already booked during this window by %s. The release was saved anyway.',
            $release->team->name,
            $list
        );
    }
}
