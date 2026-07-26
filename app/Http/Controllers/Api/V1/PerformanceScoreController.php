<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesPerformanceTeams;
use App\Http\Requests\PerformanceScoreRequest;
use App\Http\Resources\V1\PerformanceCompetencyResource;
use App\Http\Resources\V1\PerformanceScoreResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\PerformanceCompetency;
use App\Models\PerformanceScore;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use App\Support\PerformancePeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The evaluation grid and the ratings upsert.
 *
 * A score is keyed on (team, member, competency, period), where the period is
 * the day for daily competencies and the Monday–Sunday week for weekly ones.
 * Saving the same cell twice updates it; it never accumulates duplicates.
 */
class PerformanceScoreController extends ApiController
{
    use ResolvesPerformanceTeams;

    /**
     * The member × competency matrix for one team, cadence, and period, with
     * any existing ratings and a leave marker per member.
     */
    public function grid(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'cadence' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
        ]);

        $viewer = $request->user();
        $teams = $this->accessiblePerformanceTeams($viewer);

        $teamId = $this->filterId($request, 'team_id');
        $team = $teamId ? $teams->firstWhere('id', $teamId) : $teams->first();
        abort_if($teamId !== null && $team === null, 403, 'You cannot evaluate that team.');

        $cadence = $request->input('cadence') === PerformanceCompetency::CADENCE_DAILY
            ? PerformanceCompetency::CADENCE_DAILY
            : PerformanceCompetency::CADENCE_WEEKLY;

        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : today();
        $period = PerformancePeriod::normalize($cadence, $date);
        $isWeekly = $cadence === PerformanceCompetency::CADENCE_WEEKLY;

        $competencies = PerformanceCompetency::active()->forCadence($cadence)->ordered()->get();

        $rows = [];

        if ($team) {
            $scores = PerformanceScore::where('team_id', $team->id)
                ->whereDate('period_start', $period['start']->toDateString())
                ->get();

            $rowUsers = $this->rowUsersFor($team, $scores);
            $onLeave = $this->leaveFlags($team, $rowUsers, $period);

            $byUser = $scores->groupBy('user_id');

            foreach ($rowUsers as $member) {
                $memberScores = $byUser->get($member->id, collect())->keyBy('competency_id');

                $rows[] = [
                    'member' => (new UserSummaryResource($member))->resolve($request),
                    'on_leave' => $onLeave[$member->id] ?? false,
                    // Only the competencies that apply to this member's role.
                    'applicable_competency_ids' => $competencies
                        ->filter(fn (PerformanceCompetency $c) => $c->appliesToRole($member->role))
                        ->pluck('id')->values()->all(),
                    'scores' => $memberScores
                        ->map(fn (PerformanceScore $s) => (new PerformanceScoreResource($s))->resolve($request))
                        ->all(),
                ];
            }
        }

        return $this->ok([
            'teams' => TeamSummaryResource::collection($teams)->resolve($request),
            'team' => $team ? (new TeamSummaryResource($team))->resolve($request) : null,
            'cadence' => $cadence,
            'period' => [
                'type' => $period['type'],
                'date' => $date->toDateString(),
                'start' => $period['start']->toDateString(),
                'end' => $period['end']->toDateString(),
                'label' => $isWeekly
                    ? PerformancePeriod::weekLabel($date)
                    : $date->format('l, M j, Y'),
                'prev' => ($isWeekly ? $date->copy()->subWeek() : $date->copy()->subDay())->toDateString(),
                'next' => ($isWeekly ? $date->copy()->addWeek() : $date->copy()->addDay())->toDateString(),
                'is_future' => $period['start']->gt(today()),
            ],
            'competencies' => PerformanceCompetencyResource::collection($competencies)->resolve($request),
            'rows' => $rows,
        ]);
    }

    /**
     * Upsert one member's ratings for a cadence and period.
     *
     * Blank cells are skipped rather than stored as zero, so a lead may rate a
     * few competencies now and the rest later without the unrated ones dragging
     * the member's average down.
     */
    public function upsert(PerformanceScoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $viewer = $request->user();

        $period = PerformancePeriod::normalize($data['cadence'], Carbon::parse($data['date']));
        $notes = $data['notes'] ?? [];

        $saved = [];

        foreach (($data['scores'] ?? []) as $competencyId => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Manual find + save (as the web flow does) to sidestep date-cast
            // comparison quirks across drivers on the unique key.
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

            $saved[] = $score->load(['competency', 'evaluator']);
        }

        return $this->ok(
            PerformanceScoreResource::collection(collect($saved)),
            $saved ? 'Ratings saved.' : 'No ratings to save.'
        );
    }

    /**
     * The members to show: the team's active developers and QA, plus anyone
     * already scored this period — so a former member's record stays editable.
     *
     * @param  Collection<int, PerformanceScore>  $scores
     * @return Collection<int, User>
     */
    private function rowUsersFor(Team $team, $scores)
    {
        $members = $team->members()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->active()
            ->get();

        $scoredUserIds = $scores->pluck('user_id')->unique()->all();

        $extra = empty($scoredUserIds)
            ? collect()
            : User::whereIn('id', $scoredUserIds)->whereNotIn('id', $members->pluck('id')->all())->get();

        return $members->concat($extra)->unique('id')->sortBy('name')->values();
    }

    /**
     * Who was absent for the whole period — on leave with no working day in it.
     *
     * @param  Collection<int, User>  $rowUsers
     * @param  array{type: string, start: Carbon, end: Carbon}  $period
     * @return array<int, bool>
     */
    private function leaveFlags(Team $team, $rowUsers, array $period): array
    {
        // whereDate, not whereBetween — the `date` cast stores a midnight
        // timestamp, which a plain 'Y-m-d' upper bound would sort before and
        // so exclude the period's last day.
        $entries = TasksheetEntry::where('team_id', $team->id)
            ->whereIn('user_id', $rowUsers->pluck('id'))
            ->whereDate('date', '>=', $period['start']->toDateString())
            ->whereDate('date', '<=', $period['end']->toDateString())
            ->get()
            ->groupBy('user_id');

        $flags = [];

        foreach ($rowUsers as $member) {
            $memberEntries = $entries->get($member->id, collect());
            $present = $memberEntries->filter(fn (TasksheetEntry $e) => ! $e->isOnLeave())->count();
            $leave = $memberEntries->filter(fn (TasksheetEntry $e) => $e->isOnLeave())->count();

            $flags[$member->id] = $leave > 0 && $present === 0;
        }

        return $flags;
    }
}
