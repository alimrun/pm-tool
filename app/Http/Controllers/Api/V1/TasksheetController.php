<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TasksheetEntryRequest;
use App\Http\Resources\V1\TasksheetEntryResource;
use App\Http\Resources\V1\TeamSummaryResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The team's daily tasksheet.
 *
 * Two rules drive most of this file. First, history is immutable in shape: the
 * rows shown for a past date are the people whose membership *covered that
 * date*, including anyone since removed, deactivated, or deleted — otherwise a
 * departure would silently rewrite last month's sheet. Second, the `feedback`
 * column belongs to leads alone, on read (the resource omits it) and on write
 * (a member's save never touches it).
 */
class TasksheetController extends ApiController
{
    /** The day grid for one team: the member rows, their entries, and the output trend. */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'date' => ['nullable', 'date'],
        ]);

        $viewer = $request->user();

        // The viewer's own teams first, then the rest.
        $myTeamIds = $viewer->teams()->pluck('teams.id');
        $teams = Team::active()->orderBy('name')->get()
            ->sortBy(fn (Team $t) => [$myTeamIds->contains($t->id) ? 0 : 1, $t->name])
            ->values();

        $team = null;
        if ($teamId = $this->filterId($request, 'team_id')) {
            $team = $teams->firstWhere('id', $teamId);
        }
        $team ??= $teams->first();

        $day = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : today();

        $rowUsers = collect();
        $entries = collect();
        $viewerIsMember = false;

        if ($team) {
            $entries = TasksheetEntry::with('member')
                ->where('team_id', $team->id)
                ->whereDate('date', $day->toDateString())
                ->get()
                ->keyBy('user_id');

            $rowUsers = $this->rowUsersFor($team, $day, $entries);
            $viewerIsMember = $team->members()->whereKey($viewer->id)->exists();
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
                'entry' => ($e = $entries->get($u->id))
                    ? (new TasksheetEntryResource($e))->resolve($request)
                    : null,
            ])->values()->all(),
            'trend' => $team ? $this->trend($team, $day) : [],
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

        $query = TasksheetEntry::with(['team', 'member'])
            ->where('user_id', $member->id)
            ->when($this->filterId($request, 'team_id'), fn ($q, $id) => $q->where('team_id', $id))
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id');

        return $this->paginate($request, $query, TasksheetEntryResource::class);
    }

    /**
     * Save one member's row for one date.
     *
     * Matched with `whereDate` rather than `firstOrNew`: the date cast stores a
     * midnight timestamp, so an equality match on 'Y-m-d' misses the row on
     * some drivers and would create a duplicate instead of updating.
     */
    public function upsert(TasksheetEntryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $date = Carbon::parse($data['date'])->toDateString();

        $entry = TasksheetEntry::where('team_id', $data['team_id'])
            ->where('user_id', $data['user_id'])
            ->whereDate('date', $date)
            ->first() ?? new TasksheetEntry([
                'team_id' => $data['team_id'],
                'user_id' => $data['user_id'],
                'date' => $date,
            ]);

        $this->authorize('update', $entry);

        $fields = collect($data)->only([
            'plan', 'result', 'comment', 'tickets',
            'work_points', 'ticket_count', 'ticket_points', 'leave_type',
        ])->all();

        // A full day off has no task content; half-day leave keeps it, since
        // the member still works part of the day.
        if (in_array($fields['leave_type'] ?? null, TasksheetEntry::FULL_DAY_LEAVE_TYPES, true)) {
            $fields = ['leave_type' => $fields['leave_type']] + array_fill_keys(TasksheetEntry::TASK_FIELDS, null);
        }

        $entry->fill($fields);

        // Feedback is a lead's private note. A member's save never reaches it,
        // so it can be neither blanked nor forged from a member's client.
        if ($request->user()->isLead() && $request->has('feedback')) {
            $entry->feedback = $data['feedback'] ?? null;
        }

        $entry->save();

        return $this->ok(
            new TasksheetEntryResource($entry->load(['member', 'team'])),
            'Tasksheet saved.'
        );
    }

    /**
     * The rows for a given day: developers and QA whose membership covered that
     * date — including those who have since left, been deactivated, or been
     * deleted — plus anyone with a saved entry that day.
     *
     * @param  Collection<int, TasksheetEntry>  $entries
     * @return Collection<int, User>
     */
    private function rowUsersFor(Team $team, Carbon $day, $entries)
    {
        $dayStr = $day->toDateString();

        $members = $team->memberRecords()
            ->withTrashed()
            ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
            ->get()
            ->filter(fn (User $u) => ($u->pivot->left_at === null || Carbon::parse($u->pivot->left_at)->toDateString() >= $dayStr)
                && ($u->deleted_at === null || $u->deleted_at->toDateString() >= $dayStr)
                && ($u->deactivated_at === null || $u->deactivated_at->toDateString() >= $dayStr));

        return $members
            ->concat($entries->map(fn (TasksheetEntry $e) => $e->member)->filter())
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * Total work points booked per day over the trailing fortnight, for the
     * team's productivity chart.
     *
     * @return list<array<string, mixed>>
     */
    private function trend(Team $team, Carbon $day): array
    {
        $windowStart = $day->copy()->subDays(13);

        // Bounded with whereDate, not whereBetween: the `date` cast writes a
        // midnight *timestamp*, so comparing it against plain 'Y-m-d' bounds
        // sorts '2026-07-26 00:00:00' after '2026-07-26' and silently drops
        // the most recent day — the one the chart is centred on.
        $byDay = TasksheetEntry::where('team_id', $team->id)
            ->whereDate('date', '>=', $windowStart->toDateString())
            ->whereDate('date', '<=', $day->toDateString())
            ->selectRaw('date, COALESCE(SUM(work_points), 0) as wp')
            ->groupBy('date')
            ->get()
            ->mapWithKeys(fn ($r) => [Carbon::parse($r->date)->toDateString() => (int) $r->wp]);

        $trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $day->copy()->subDays($i);
            $trend[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('j'),
                'dow' => $d->format('D'),
                'work_points' => $byDay[$d->toDateString()] ?? 0,
                'is_current' => $d->isSameDay($day),
            ];
        }

        return $trend;
    }
}
