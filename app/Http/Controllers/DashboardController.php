<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Services\OverlapChecker;
use App\Support\Timeline;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OverlapChecker $overlap): View
    {
        // Developers/QA get a personal "what am I doing today" dashboard
        // instead of the release-planning timeline.
        if ($request->user()->hasLimitedAccess()) {
            return $this->memberDashboard($request);
        }

        $year = (int) $request->integer('year', (int) now()->year);

        // Fresh visit (no quarter param) → current quarter. An explicit empty
        // quarter (the "All quarters" option) still means the whole year.
        $quarter = $request->has('quarter')
            ? ($request->filled('quarter') ? (int) $request->integer('quarter') : null)
            : (int) now()->quarter;
        $projectId = $request->filled('project_id') ? (int) $request->integer('project_id') : null;
        $teamId = $request->filled('team_id') ? (int) $request->integer('team_id') : null;
        $groupBy = $request->input('group_by') === 'project' ? 'project' : 'team';

        // The axis: whole year, or one quarter of it when a quarter is chosen.
        [$rangeStart, $rangeEnd] = $this->rangeFor($year, $quarter);

        // Releases whose window intersects the axis, plus optional filters.
        $releases = Release::query()
            ->with(['project', 'team', 'phases'])
            ->whereNull('completed_at') // completed releases drop off the timeline
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('start_date')
            ->get();

        // Conflicts are computed across the team's ENTIRE schedule, not just the
        // filtered view, so a bar is flagged even if its conflicting partner is
        // off-screen. We fetch each visible team's releases once for this.
        $conflicts = $this->conflictFlags($releases, $overlap);

        $groups = $this->buildGroups($releases, $groupBy, $rangeStart, $rangeEnd, $conflicts);

        return view('dashboard', [
            'groups' => $groups,
            'months' => Timeline::monthColumns($rangeStart, $rangeEnd),
            'rangeStart' => $rangeStart,
            'rangeEnd' => $rangeEnd,
            'analytics' => $this->analytics($year, $quarter, $rangeStart, $rangeEnd, $projectId, $teamId, $conflicts, $releases),
            'phaseColors' => Release::PHASE_COLORS,
            'phaseLabels' => Release::PHASES,
            'hasConflicts' => in_array(true, $conflicts, true),
            'filters' => [
                'year' => $year,
                'quarter' => $quarter,
                'project_id' => $projectId,
                'team_id' => $teamId,
                'group_by' => $groupBy,
            ],
            'years' => $this->availableYears($year),
            'projects' => Project::orderBy('name')->get(),
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    /**
     * Headline metrics + chart series for the planning dashboard. Scoped to the
     * selected year and the project/team filters so the numbers agree with the
     * timeline below them. The conflict figure reflects the current timeline view.
     *
     * @param  array<int, bool>  $conflicts
     * @param  Collection<int, Release>  $timelineReleases
     * @return array<string, mixed>
     */
    private function analytics(int $year, ?int $quarter, Carbon $rangeStart, Carbon $rangeEnd, ?int $projectId, ?int $teamId, array $conflicts, $timelineReleases): array
    {
        $periodLabel = $quarter !== null ? 'Q'.$quarter.' '.$year : (string) $year;

        // Every release whose window overlaps the selected range (+ filters), so the
        // cards and charts match the timeline for the same year/quarter/project/team.
        $rangeReleases = Release::query()
            ->with('team:id,name,color')
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->whereDate('end_date', '>=', $rangeStart->toDateString())
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->get();

        $ongoing = $rangeReleases->whereNull('completed_at');

        // Release load: how many releases are in flight during each month the range spans
        // (12 columns for a whole year, 3 for a single quarter).
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
                'current' => $ms->month === (int) now()->month && $ms->year === (int) now()->year,
            ];
            $cursor->addMonth();
        }
        $monthlyMax = max(array_column($monthly, 'count') ?: [0]);

        // Delivery: task-status mix across the ongoing releases in view.
        $statusOrder = array_keys(Task::STATUSES);
        $raw = Task::query()
            ->whereIn('release_id', $ongoing->pluck('id'))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statusCounts = [];
        foreach ($statusOrder as $status) {
            $statusCounts[$status] = (int) ($raw[$status] ?? 0);
        }
        $taskTotal = array_sum($statusCounts);

        // Team workload: active releases owned by each team, busiest first.
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

        // Conflicts as flagged on the timeline (release count + distinct teams).
        $conflictedIds = array_keys(array_filter($conflicts));
        $teamsDoubleBooked = $timelineReleases
            ->whereIn('id', $conflictedIds)
            ->pluck('team_id')->unique()->count();

        return [
            'year' => $year,
            'periodLabel' => $periodLabel,
            'active' => $ongoing->count(),
            'completedThisYear' => Release::completed()
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
            'conflictCount' => count($conflictedIds),
            'teamsDoubleBooked' => $teamsDoubleBooked,
            'monthly' => $monthly,
            'monthlyMax' => $monthlyMax,
            'statusCounts' => $statusCounts,
            'statusLabels' => Task::STATUSES,
            'taskTotal' => $taskTotal,
            'donePct' => $taskTotal > 0
                ? (int) round(($statusCounts['done'] + $statusCounts['archive']) / $taskTotal * 100)
                : 0,
            'teamWorkload' => $teamWorkload,
            'teamWorkloadMax' => collect($teamWorkload)->max('count') ?: 0,
        ];
    }

    private function memberDashboard(Request $request): View
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

        // My tasksheet status for today, one line per team.
        $teams = $user->teams()->orderBy('name')->get();
        $sheetEntries = TasksheetEntry::where('user_id', $user->id)
            ->whereIn('team_id', $teams->pluck('id'))
            ->whereDate('date', today()->toDateString())
            ->get()
            ->keyBy('team_id');

        // Meetings I'm attending (or created) in the next 14 days.
        $meetings = Event::where('type', 'meeting')
            ->whereBetween('starts_at', [now(), now()->addDays(14)])
            ->where(fn ($q) => $q
                ->whereHas('attendees', fn ($a) => $a->whereKey($user->id))
                ->orWhere('created_by', $user->id))
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        return view('dashboard-member', [
            'tasks' => $tasks,
            'teams' => $teams,
            'sheetEntries' => $sheetEntries,
            'meetings' => $meetings,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rangeFor(int $year, ?int $quarter): array
    {
        if ($quarter !== null) {
            $startMonth = ($quarter - 1) * 3 + 1;
            $start = Carbon::create($year, $startMonth, 1)->startOfDay();
            $end = $start->copy()->addMonths(2)->endOfMonth();

            return [$start, $end];
        }

        return [
            Carbon::create($year, 1, 1)->startOfDay(),
            Carbon::create($year, 12, 31)->endOfDay(),
        ];
    }

    /**
     * Flag every visible release that overlaps another release owned by the same
     * team, considering the team's full schedule (including off-screen releases).
     *
     * @param  Collection<int, Release>  $releases
     * @return array<int, bool>
     */
    private function conflictFlags($releases, OverlapChecker $overlap): array
    {
        $flags = [];
        $teamIds = $releases->pluck('team_id')->unique();

        foreach ($teamIds as $teamId) {
            $teamReleases = Release::where('team_id', $teamId)->whereNull('completed_at')->get();
            foreach ($overlap->flagConflicts($teamReleases) as $releaseId => $flag) {
                $flags[$releaseId] = $flag;
            }
        }

        return $flags;
    }

    /**
     * Group releases (by team or project) and attach timeline geometry.
     *
     * @param  Collection<int, Release>  $releases
     * @param  array<int, bool>  $conflicts
     */
    private function buildGroups($releases, string $groupBy, Carbon $rangeStart, Carbon $rangeEnd, array $conflicts): array
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
                        $phase->start_date, $phase->end_date,
                        $release->start_date, $release->end_date
                    );
                    $phaseSegments[] = [
                        'phase' => $phase->phase,
                        'label' => $phase->label(),
                        'offset' => $rel['offset'],
                        'width' => $rel['width'],
                        'color' => Release::PHASE_COLORS[$phase->phase] ?? '#94a3b8',
                        'start' => $phase->start_date,
                        'end' => $phase->end_date,
                    ];
                }

                $bars[] = [
                    'release' => $release,
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
                'label' => $owner->name,
                'color' => $owner->color,
                'type' => $groupBy,
                'id' => $owner->id,
                'bars' => $bars,
            ];
        }

        usort($groups, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $groups;
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(int $selected): array
    {
        $min = (int) (Release::min('year') ?? $selected);
        $max = (int) (Release::max('year') ?? $selected);
        $min = min($min, $selected, (int) now()->year);
        $max = max($max, $selected, (int) now()->year);

        return range($min, $max);
    }
}
