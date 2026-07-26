<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Http\Resources\V1\TasksheetEntryResource;
use App\Http\Resources\V1\TaskSummaryResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Models\Event;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Services\OverlapChecker;
use App\Support\Timeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The dashboard, in the two forms the product has: the planning timeline for
 * full-access roles, and the personal "what am I doing today" view for
 * developers and QA.
 *
 * The timeline is returned as *computed geometry* — bar offsets and widths as
 * percentages of the axis, phase segments positioned inside their bar, and
 * conflict flags — rather than as raw releases for the client to lay out. That
 * is deliberate: the alternative is every client reimplementing Timeline and
 * OverlapChecker, which is exactly how two surfaces end up drawing different
 * pictures of the same schedule.
 */
class DashboardController extends ApiController
{
    public function __invoke(Request $request, OverlapChecker $overlap): JsonResponse
    {
        return $request->user()->hasLimitedAccess()
            ? $this->memberDashboard($request)
            : $this->planningDashboard($request, $overlap);
    }

    private function planningDashboard(Request $request, OverlapChecker $overlap): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $year = (int) $request->integer('year', (int) now()->year);

        // No `quarter` parameter at all → this quarter. An explicitly empty one
        // is the "all quarters" choice and means the whole year.
        $quarter = $request->has('quarter')
            ? ($request->filled('quarter') ? (int) $request->integer('quarter') : null)
            : (int) now()->quarter;

        $projectId = $this->filterId($request, 'project_id');
        $teamId = $this->filterId($request, 'team_id');
        $groupBy = $request->input('group_by') === 'project' ? 'project' : 'team';

        [$rangeStart, $rangeEnd] = $this->rangeFor($year, $quarter);

        $releases = Release::query()
            ->with(['project', 'team', 'phases'])
            ->whereNull('completed_at') // shipped releases drop off the timeline
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('start_date')
            ->get();

        $conflicts = $this->conflictFlags($releases, $overlap);

        return $this->ok([
            'mode' => 'planning',
            'range' => [
                'start' => $rangeStart->toDateString(),
                'end' => $rangeEnd->toDateString(),
            ],
            'months' => Timeline::monthColumns($rangeStart, $rangeEnd),
            'groups' => $this->buildGroups($request, $releases, $groupBy, $rangeStart, $rangeEnd, $conflicts),
            'has_conflicts' => in_array(true, $conflicts, true),
            'conflicting_release_ids' => array_map('intval', array_keys(array_filter($conflicts))),
            'analytics' => $this->analytics($year, $quarter, $rangeStart, $rangeEnd, $projectId, $teamId, $conflicts, $releases),
            'phase_colors' => Release::PHASE_COLORS,
            'phase_labels' => Release::PHASES,
            'filters' => [
                'year' => $year,
                'quarter' => $quarter,
                'project_id' => $projectId,
                'team_id' => $teamId,
                'group_by' => $groupBy,
            ],
            'years' => $this->availableYears($year),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'color']),
            'teams' => TeamSummaryResource::collection(Team::orderBy('name')->get())->resolve($request),
        ]);
    }

    /** The personal dashboard developers and QA get instead of the timeline. */
    private function memberDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        // Open tasks assigned to me — most urgent first, undated last.
        $tasks = Task::with('release')
            ->where('assignee_id', $user->id)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $teams = $user->teams()->orderBy('name')->get();

        $sheetEntries = TasksheetEntry::where('user_id', $user->id)
            ->whereIn('team_id', $teams->pluck('id'))
            ->whereDate('date', today()->toDateString())
            ->get()
            ->keyBy('team_id');

        // Meetings I'm attending (or created) in the next fortnight.
        $meetings = Event::with(['release', 'creator'])
            ->where('type', 'meeting')
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->where(fn ($q) => $q
                ->whereHas('attendees', fn ($a) => $a->whereKey($user->id))
                ->orWhere('created_by', $user->id))
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        return $this->ok([
            'mode' => 'member',
            'date' => today()->toDateString(),
            'my_tasks' => TaskSummaryResource::collection($tasks)->resolve($request),
            'overdue_count' => $tasks->filter(
                fn (Task $t) => $t->due_date && $t->due_date->lt(today())
            )->count(),
            'tasksheet_today' => $teams->map(fn (Team $team) => [
                'team' => (new TeamSummaryResource($team))->resolve($request),
                'entry' => ($e = $sheetEntries->get($team->id))
                    ? (new TasksheetEntryResource($e))->resolve($request)
                    : null,
            ])->values()->all(),
            'upcoming_meetings' => EventResource::collection($meetings)->resolve($request),
        ]);
    }

    /**
     * Headline metrics and chart series, scoped to the same year/quarter and
     * filters as the timeline below them so the numbers and the bars agree.
     *
     * @param  array<int, bool>  $conflicts
     * @param  Collection<int, Release>  $timelineReleases
     * @return array<string, mixed>
     */
    private function analytics(int $year, ?int $quarter, Carbon $rangeStart, Carbon $rangeEnd, ?int $projectId, ?int $teamId, array $conflicts, $timelineReleases): array
    {
        $rangeReleases = Release::query()
            ->with('team:id,name,color')
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->get();

        $ongoing = $rangeReleases->whereNull('completed_at');

        // How many releases are in flight in each month the range spans.
        $monthly = [];
        $cursor = $rangeStart->copy()->startOfMonth();
        $lastMonth = $rangeEnd->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $ms = $cursor->copy()->startOfMonth();
            $me = $cursor->copy()->endOfMonth();

            $monthly[] = [
                'label' => $ms->format('M'),
                'count' => $rangeReleases->filter(
                    fn (Release $r) => $r->start_date <= $me && $r->end_date >= $ms
                )->count(),
                'is_current' => $ms->month === (int) now()->month && $ms->year === (int) now()->year,
            ];

            $cursor->addMonth();
        }

        // Task-status mix across the ongoing releases in view.
        $raw = Task::query()
            ->whereIn('release_id', $ongoing->pluck('id'))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = [];
        foreach (array_keys(Task::STATUSES) as $status) {
            $statusCounts[$status] = (int) ($raw[$status] ?? 0);
        }
        $taskTotal = array_sum($statusCounts);

        $teamWorkload = $ongoing
            ->groupBy('team_id')
            ->map(fn ($rs) => [
                'name' => $rs->first()->team->name,
                'color' => $rs->first()->team->color,
                'count' => $rs->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();

        $conflictedIds = array_keys(array_filter($conflicts));

        return [
            'year' => $year,
            'period_label' => $quarter !== null ? 'Q'.$quarter.' '.$year : (string) $year,
            'active' => $ongoing->count(),
            'completed_in_period' => Release::completed()
                ->whereDate('completed_at', '>=', $rangeStart->toDateString())
                ->whereDate('completed_at', '<=', $rangeEnd->toDateString())
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
                ->count(),
            'upcoming' => Release::ongoing()
                ->whereDate('start_date', '>=', now()->toDateString())
                ->whereDate('start_date', '<=', now()->addDays(30)->toDateString())
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
                ->count(),
            'conflict_count' => count($conflictedIds),
            'teams_double_booked' => $timelineReleases
                ->whereIn('id', $conflictedIds)
                ->pluck('team_id')->unique()->count(),
            'monthly' => $monthly,
            'monthly_max' => max(array_column($monthly, 'count') ?: [0]),
            'status_counts' => $statusCounts,
            'task_total' => $taskTotal,
            'done_pct' => $taskTotal > 0
                ? (int) round(($statusCounts['done'] + $statusCounts['archive']) / $taskTotal * 100)
                : 0,
            'team_workload' => $teamWorkload,
            'team_workload_max' => collect($teamWorkload)->max('count') ?: 0,
        ];
    }

    /**
     * The axis: a whole year, or one quarter of it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeFor(int $year, ?int $quarter): array
    {
        if ($quarter !== null) {
            $start = Carbon::create($year, ($quarter - 1) * 3 + 1, 1)->startOfDay();

            return [$start, $start->copy()->addMonths(2)->endOfMonth()];
        }

        return [
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
        ];
    }

    /**
     * Flag every visible release that overlaps another owned by the same team.
     *
     * Conflicts are computed across each team's *entire* schedule, not just the
     * filtered view — otherwise a bar would look clear simply because the
     * release it collides with is off-screen.
     *
     * @param  Collection<int, Release>  $releases
     * @return array<int, bool>
     */
    private function conflictFlags($releases, OverlapChecker $overlap): array
    {
        $flags = [];

        foreach ($releases->pluck('team_id')->unique() as $teamId) {
            $teamReleases = Release::where('team_id', $teamId)->whereNull('completed_at')->get();

            foreach ($overlap->flagConflicts($teamReleases) as $releaseId => $flag) {
                $flags[$releaseId] = $flag;
            }
        }

        return $flags;
    }

    /**
     * Group releases by team or project and attach the timeline geometry each
     * bar needs: its offset and width on the axis, and its phase segments
     * positioned relative to the bar itself.
     *
     * @param  Collection<int, Release>  $releases
     * @param  array<int, bool>  $conflicts
     * @return list<array<string, mixed>>
     */
    private function buildGroups(Request $request, $releases, string $groupBy, Carbon $rangeStart, Carbon $rangeEnd, array $conflicts): array
    {
        $grouped = $releases->groupBy(fn (Release $r) => $groupBy === 'project' ? $r->project_id : $r->team_id);

        $groups = [];

        foreach ($grouped as $items) {
            $owner = $groupBy === 'project' ? $items->first()->project : $items->first()->team;

            $bars = [];

            foreach ($items as $release) {
                $seg = Timeline::segment($release->start_date, $release->end_date, $rangeStart, $rangeEnd);

                if (! $seg['visible']) {
                    continue;
                }

                $phaseSegments = [];
                foreach ($release->phases as $phase) {
                    $rel = Timeline::relativeSegment(
                        $phase->start_date,
                        $phase->end_date,
                        $release->start_date,
                        $release->end_date
                    );

                    $phaseSegments[] = [
                        'phase' => $phase->phase,
                        'label' => $phase->label(),
                        'offset' => $rel['offset'],
                        'width' => $rel['width'],
                        'color' => Release::PHASE_COLORS[$phase->phase] ?? '#94a3b8',
                        'start_date' => $phase->start_date?->toDateString(),
                        'end_date' => $phase->end_date?->toDateString(),
                    ];
                }

                $bars[] = [
                    'release' => (new ReleaseSummaryResource($release))->resolve($request),
                    'offset' => $seg['offset'],
                    'width' => $seg['width'],
                    'conflict' => $conflicts[$release->id] ?? false,
                    'phases' => $phaseSegments,
                ];
            }

            if ($bars === []) {
                continue;
            }

            $groups[] = [
                'id' => $owner->id,
                'type' => $groupBy,
                'label' => $owner->name,
                'color' => $owner->color,
                'bars' => $bars,
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $groups;
    }

    /** @return list<int> */
    private function availableYears(int $selected): array
    {
        $min = (int) (Release::min('year') ?? $selected);
        $max = (int) (Release::max('year') ?? $selected);

        return range(
            min($min, $selected, (int) now()->year),
            max($max, $selected, (int) now()->year)
        );
    }
}
