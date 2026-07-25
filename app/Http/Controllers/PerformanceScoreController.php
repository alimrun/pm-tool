<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPerformanceTeams;
use App\Http\Requests\PerformanceScoreRequest;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\PerformancePeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class PerformanceScoreController extends Controller
{
    use ResolvesPerformanceTeams;

    /**
     * The evaluation grid: a member × competency matrix for one team, cadence,
     * and period, where the assigned lead records 1–5 ratings.
     */
    public function evaluate(Request $request): View
    {
        $viewer = $request->user();
        $teams = $this->accessiblePerformanceTeams($viewer);
        $team = $this->resolvePerformanceTeam($teams, $request->integer('team') ?: null);

        $cadence = $request->input('cadence') === PerformanceCompetency::CADENCE_DAILY
            ? PerformanceCompetency::CADENCE_DAILY
            : PerformanceCompetency::CADENCE_WEEKLY;

        $date = ($d = $request->input('date')) ? Carbon::parse($d)->startOfDay() : today();
        $period = PerformancePeriod::normalize($cadence, $date);
        $isWeekly = $cadence === PerformanceCompetency::CADENCE_WEEKLY;

        $competencies = PerformanceCompetency::active()->forCadence($cadence)->ordered()->get();

        $rowUsers = collect();
        $scores = collect();
        $onLeave = [];

        if ($team) {
            $scores = PerformanceScore::where('team_id', $team->id)
                ->whereDate('period_start', $period['start']->toDateString())
                ->get()
                ->keyBy(fn (PerformanceScore $s) => $s->user_id.'-'.$s->competency_id);

            $members = $team->members()
                ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
                ->active()
                ->get();

            // Anyone already scored this period keeps their row (former members).
            $scoredUserIds = $scores->map(fn (PerformanceScore $s) => $s->user_id)->unique();
            $extra = $scoredUserIds->diff($members->pluck('id'))->isEmpty()
                ? collect()
                : User::whereIn('id', $scoredUserIds)->get();

            $rowUsers = $members->concat($extra)->unique('id')->sortBy('name')->values();

            // Leave badges: on-leave for the day (daily) or the whole week (weekly).
            $entries = TasksheetEntry::where('team_id', $team->id)
                ->whereIn('user_id', $rowUsers->pluck('id'))
                ->whereBetween('date', [$period['start']->toDateString(), $period['end']->toDateString()])
                ->get()
                ->groupBy('user_id');

            foreach ($rowUsers as $u) {
                $es = $entries->get($u->id, collect());
                $present = $es->filter(fn (TasksheetEntry $e) => ! $e->isOnLeave())->count();
                $leave = $es->filter(fn (TasksheetEntry $e) => $e->isOnLeave())->count();
                $onLeave[$u->id] = $leave > 0 && $present === 0;
            }
        }

        return view('performance.evaluate', [
            'teams' => $teams,
            'team' => $team,
            'cadence' => $cadence,
            'isWeekly' => $isWeekly,
            'date' => $date,
            'period' => $period,
            'periodLabel' => $isWeekly ? PerformancePeriod::weekLabel($date) : $date->format('l, M j, Y'),
            'prev' => $isWeekly ? $date->copy()->subWeek() : $date->copy()->subDay(),
            'next' => $isWeekly ? $date->copy()->addWeek() : $date->copy()->addDay(),
            'isFuture' => $period['start']->gt(today()),
            'competencies' => $competencies,
            'rowUsers' => $rowUsers,
            'scores' => $scores,
            'onLeave' => $onLeave,
        ]);
    }

    /** Upsert one member's row of ratings for a cadence + period. */
    public function upsert(PerformanceScoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $viewer = $request->user();

        $cadence = $data['cadence'];
        $period = PerformancePeriod::normalize($cadence, Carbon::parse($data['date']));
        $notes = $data['notes'] ?? [];

        $saved = 0;
        foreach (($data['scores'] ?? []) as $competencyId => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Manual find + save (mirrors the tasksheet) to sidestep date-cast
            // comparison quirks across DB drivers on the unique key.
            $score = PerformanceScore::where('team_id', $data['team_id'])
                ->where('user_id', $data['user_id'])
                ->where('competency_id', $competencyId)
                ->whereDate('period_start', $period['start']->toDateString())
                ->first()
                ?? new PerformanceScore([
                    'team_id' => $data['team_id'],
                    'user_id' => $data['user_id'],
                    'competency_id' => $competencyId,
                ]);

            // Authorize against the (possibly unsaved) row's team.
            $score->setRelation('team', Team::find($data['team_id']));
            $this->authorize('update', $score);

            $score->fill([
                'evaluator_id' => $viewer->id,
                'period_type' => $period['type'],
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'score' => (int) $value,
                'note' => $notes[$competencyId] ?? null,
            ])->save();

            $saved++;
        }

        return redirect()->route('performance.evaluate', [
            'team' => $data['team_id'],
            'cadence' => $cadence,
            'date' => $period['start']->toDateString(),
        ])->with('success', $saved > 0 ? 'Ratings saved.' : 'No ratings to save.');
    }
}
