<?php

namespace App\Services;

use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The team's daily tasksheet.
 *
 * Two rules drive most of this class:
 *
 * 1. **History does not rewrite itself.** The rows for a past date are the
 *    people whose membership *covered that date* — including anyone since
 *    removed, deactivated, or deleted. If departures pruned the roster
 *    retroactively, last month's sheet would silently change.
 * 2. **`feedback` belongs to leads alone.** A member's save never reaches it,
 *    so it can be neither blanked nor forged from a member's client.
 *
 * Note the `whereDate` bounds in trend(): the model's `date` cast stores a
 * midnight *timestamp*, so comparing it against plain 'Y-m-d' bounds sorts
 * '2026-07-26 00:00:00' after '2026-07-26' and silently drops the most recent
 * day — the very day the chart is centred on.
 */
class TasksheetService
{
    /** How many days of output the trend chart covers, including the viewed day. */
    public const TREND_DAYS = 14;

    /** Active teams, the viewer's own first. */
    public function teamsFor(User $viewer): Collection
    {
        $myTeamIds = $viewer->teams()->pluck('teams.id');

        return Team::active()->orderBy('name')->get()
            ->sortBy(fn (Team $t) => [$myTeamIds->contains($t->id) ? 0 : 1, $t->name])
            ->values();
    }

    /**
     * Resolve the selected team within the set the viewer may pick from,
     * falling back to the first.
     *
     * @param  Collection<int, Team>  $teams
     */
    public function resolveTeam(Collection $teams, ?int $teamId): ?Team
    {
        return ($teamId ? $teams->firstWhere('id', $teamId) : null) ?? $teams->first();
    }

    /**
     * That day's saved rows for a team, keyed by user.
     *
     * @return Collection<int, TasksheetEntry>
     */
    public function entriesFor(Team $team, Carbon $day): Collection
    {
        return TasksheetEntry::with('member')
            ->where('team_id', $team->id)
            ->whereDate('date', $day->toDateString())
            ->get()
            ->keyBy('user_id');
    }

    /**
     * The people who belong on a given day's sheet: developers and QA whose
     * membership covered that date, plus anyone with a saved entry that day.
     *
     * @param  Collection<int, TasksheetEntry>  $entries
     * @return Collection<int, User>
     */
    public function rowUsersFor(Team $team, Carbon $day, Collection $entries): Collection
    {
        $dayStr = $day->toDateString();

        $members = $team->memberRecords()
            ->withTrashed()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->get()
            ->filter(fn (User $u) => $this->coveredDate($u, $dayStr));

        return $members
            ->concat($entries->map(fn (TasksheetEntry $e) => $e->member)->filter())
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Total work points booked per day over the trailing fortnight, ending on
     * the viewed day, with quiet days filled as zero so the axis is continuous.
     *
     * @return list<array<string, mixed>>
     */
    public function trend(Team $team, Carbon $day): array
    {
        $span = self::TREND_DAYS - 1;
        $windowStart = $day->copy()->subDays($span);

        $byDay = TasksheetEntry::where('team_id', $team->id)
            ->whereDate('date', '>=', $windowStart->toDateString())
            ->whereDate('date', '<=', $day->toDateString())
            ->selectRaw('date, COALESCE(SUM(work_points), 0) as wp')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->date)->toDateString() => (int) $r->wp]);

        $trend = [];
        for ($i = $span; $i >= 0; $i--) {
            $d = $day->copy()->subDays($i);

            $trend[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('j'),
                'dow' => $d->format('D'),
                'wp' => $byDay[$d->toDateString()] ?? 0,
                'current' => $d->isSameDay($day),
            ];
        }

        return $trend;
    }

    /**
     * One member's history, filterable by team and date range.
     *
     * @return Builder<TasksheetEntry>
     */
    public function history(User $member, ?int $teamId = null, ?string $from = null, ?string $to = null): Builder
    {
        return TasksheetEntry::with(['team', 'member'])
            ->where('user_id', $member->id)
            ->when($teamId, fn ($q, $id) => $q->where('team_id', $id))
            ->when($from, fn ($q, $date) => $q->whereDate('date', '>=', $date))
            ->when($to, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    /** The teams a member has ever booked time against. */
    public function teamsWithHistory(User $member): Collection
    {
        return Team::whereIn('id', TasksheetEntry::where('user_id', $member->id)->select('team_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Find or build the row for a (team, member, date) so the caller can
     * authorize it before the write.
     *
     * Matched with `whereDate` rather than `firstOrNew`: the date cast stores a
     * midnight timestamp, so an equality match on 'Y-m-d' misses the row on
     * some drivers and would insert a duplicate instead of updating.
     *
     * @param  array{team_id: int|string, user_id: int|string, date: string}  $data
     */
    public function resolveEntry(array $data): TasksheetEntry
    {
        $date = Carbon::parse($data['date'])->toDateString();

        return TasksheetEntry::where('team_id', $data['team_id'])
            ->where('user_id', $data['user_id'])
            ->whereDate('date', $date)
            ->first() ?? new TasksheetEntry([
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'date' => $date,
            ]);
    }

    /**
     * Save a row.
     *
     * A full-day leave (casual/sick) clears the task fields — an absent member
     * has no task content. Half-day leave keeps them, since the member still
     * works part of the day. `feedback` is applied only for a lead, and only
     * when the key was actually submitted, so an omitted field is left alone
     * rather than nulled.
     *
     * @param  array<string, mixed>  $data  validated request data
     */
    public function save(TasksheetEntry $entry, array $data, User $actor, bool $feedbackSubmitted = false): TasksheetEntry
    {
        $fields = collect($data)->only([...TasksheetEntry::TASK_FIELDS, 'leave_type'])->all();

        if (in_array($fields['leave_type'] ?? null, TasksheetEntry::FULL_DAY_LEAVE_TYPES, true)) {
            $fields = ['leave_type' => $fields['leave_type']]
                + array_fill_keys(TasksheetEntry::TASK_FIELDS, null);
        }

        $entry->fill($fields);

        if ($actor->isLead() && $feedbackSubmitted) {
            $entry->feedback = $data['feedback'] ?? null;
        }

        $entry->save();

        return $entry;
    }

    /**
     * Whether a membership record covers a given day — the person had not left,
     * been deleted, or been deactivated before it.
     */
    private function coveredDate(User $member, string $dayStr): bool
    {
        $leftAt = $member->pivot->left_at;

        return ($leftAt === null || Carbon::parse($leftAt)->toDateString() >= $dayStr)
            && ($member->deleted_at === null || $member->deleted_at->toDateString() >= $dayStr)
            && ($member->deactivated_at === null || $member->deactivated_at->toDateString() >= $dayStr);
    }
}
