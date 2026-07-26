<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TasksheetEntryRequest;
use App\Http\Resources\V1\TasksheetEntryResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\User;
use App\Services\TasksheetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * The team's daily tasksheet.
 *
 * The roster rule (who belongs on a given day's sheet), the output trend, and
 * the lead-only `feedback` write all live in TasksheetService, shared with the
 * Blade tasksheet. The resource omits `feedback` for a non-lead reader, and the
 * service ignores it on a non-lead write — read and write are guarded
 * independently, so neither alone is load-bearing.
 */
class TasksheetController extends ApiController
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    /** The day grid for one team: member rows, their entries, and the trend. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'date' => ['nullable', 'date'],
        ]);

        $viewer = $request->user();

        $teams = $this->tasksheet->teamsFor($viewer);
        $team = $this->tasksheet->resolveTeam($teams, $this->filterId($request, 'team_id'));

        $day = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $entries = collect();
        $rowUsers = collect();
        $viewerIsMember = false;
        $trend = [];

        if ($team) {
            $entries = $this->tasksheet->entriesFor($team, $day);
            $rowUsers = $this->tasksheet->rowUsersFor($team, $day, $entries);
            $viewerIsMember = $team->members()->whereKey($viewer->id)->exists();
            $trend = $this->tasksheet->trend($team, $day);
        }

        return $this->ok([
            'teams' => TeamSummaryResource::collection($teams)->resolve($request),
            'team' => $team ? (new TeamSummaryResource($team))->resolve($request) : null,
            'date' => $day->toDateString(),
            'is_today' => $day->isToday(),
            'is_past' => $day->lt(today()),
            'viewer_is_member' => $viewerIsMember,
            'can_write_feedback' => $viewer->isLead(),
            'rows' => $rowUsers->map(fn (User $u) => [
                'user' => (new UserSummaryResource($u))->resolve($request),
                'entry' => ($entry = $entries->get($u->id))
                    ? (new TasksheetEntryResource($entry))->resolve($request)
                    : null,
            ])->values()->all(),
            'trend' => $this->presentTrend($trend),
        ]);
    }

    /**
     * One member's history. Readable by that member and by leads only — a
     * teammate's daily log is not open reading.
     */
    public function user(Request $request, User $member): AnonymousResourceCollection
    {
        $viewer = $request->user();
        abort_unless($viewer->isLead() || $viewer->id === $member->id, 403);

        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $query = $this->tasksheet->history($member, $this->filterId($request, 'team_id'), $from, $to);

        return $this->paginate($request, $query, TasksheetEntryResource::class);
    }

    /** Save one member's row for one date. */
    public function upsert(TasksheetEntryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $entry = $this->tasksheet->resolveEntry($data);
        $this->authorize('update', $entry);

        $this->tasksheet->save($entry, $data, $request->user(), $request->has('feedback'));

        return $this->ok(
            new TasksheetEntryResource($entry->load(['member', 'team'])),
            'Tasksheet saved.'
        );
    }

    /**
     * The shared trend uses the keys the Blade chart reads (`wp`, `current`);
     * this API's published contract names them `work_points` and `is_current`.
     *
     * @param  list<array<string, mixed>>  $trend
     * @return list<array<string, mixed>>
     */
    private function presentTrend(array $trend): array
    {
        return array_map(fn (array $day) => [
            'date' => $day['date'],
            'label' => $day['label'],
            'dow' => $day['dow'],
            'work_points' => $day['wp'],
            'is_current' => $day['current'],
        ], $trend);
    }
}
