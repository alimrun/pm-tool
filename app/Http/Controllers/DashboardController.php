<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Services\OverlapChecker;
use App\Support\Timeline;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, OverlapChecker $overlap): View
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $quarter = $request->filled('quarter') ? (int) $request->integer('quarter') : null;
        $projectId = $request->filled('project_id') ? (int) $request->integer('project_id') : null;
        $teamId = $request->filled('team_id') ? (int) $request->integer('team_id') : null;
        $groupBy = $request->input('group_by') === 'project' ? 'project' : 'team';

        // The axis: whole year, or one quarter of it when a quarter is chosen.
        [$rangeStart, $rangeEnd] = $this->rangeFor($year, $quarter);

        // Releases whose window intersects the axis, plus optional filters.
        $releases = Release::query()
            ->with(['project', 'team', 'phases'])
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
     * @param  \Illuminate\Support\Collection<int, Release>  $releases
     * @return array<int, bool>
     */
    private function conflictFlags($releases, OverlapChecker $overlap): array
    {
        $flags = [];
        $teamIds = $releases->pluck('team_id')->unique();

        foreach ($teamIds as $teamId) {
            $teamReleases = Release::where('team_id', $teamId)->get();
            foreach ($overlap->flagConflicts($teamReleases) as $releaseId => $flag) {
                $flags[$releaseId] = $flag;
            }
        }

        return $flags;
    }

    /**
     * Group releases (by team or project) and attach timeline geometry.
     *
     * @param  \Illuminate\Support\Collection<int, Release>  $releases
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
