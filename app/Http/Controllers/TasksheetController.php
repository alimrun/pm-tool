<?php

namespace App\Http\Controllers;

use App\Http\Requests\TasksheetEntryRequest;
use App\Models\TasksheetEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TasksheetController extends Controller
{
    public function index(): View
    {
        $viewer = request()->user();

        // Viewer's teams first, then the other active teams.
        $myTeamIds = $viewer->teams()->pluck('teams.id');
        $teams = Team::active()->orderBy('name')->get()
            ->sortBy(fn (Team $t) => [$myTeamIds->contains($t->id) ? 0 : 1, $t->name])
            ->values();

        $team = null;
        if ($teamId = request()->integer('team')) {
            $team = $teams->firstWhere('id', $teamId);
        }
        $team ??= $teams->first();

        $day = ($d = request('date')) ? Carbon::parse($d)->startOfDay() : today();
        $isPast = $day->lt(today());

        $rowUsers = collect();
        $entries = collect();

        if ($team) {
            $entries = TasksheetEntry::with('member')
                ->where('team_id', $team->id)
                ->whereDate('date', $day->toDateString())
                ->get()
                ->keyBy('user_id');

            // Rows: current dev/QA members ∪ anyone with a saved entry that day
            // (former members keep their historical rows).
            $members = $team->members()
                ->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])
                ->active()
                ->get();

            $rowUsers = $members
                ->concat($entries->map(fn (TasksheetEntry $e) => $e->member)->filter())
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        // Team output for the trailing 14 days (ending on the viewed day): total
        // work points booked per calendar day, for the productivity chart.
        $trend = [];
        if ($team) {
            $windowStart = $day->copy()->subDays(13);
            $byDay = TasksheetEntry::where('team_id', $team->id)
                ->whereBetween('date', [$windowStart->toDateString(), $day->toDateString()])
                ->selectRaw('date, COALESCE(SUM(work_points), 0) as wp')
                ->groupBy('date')
                ->get()
                ->mapWithKeys(fn ($r) => [Carbon::parse($r->date)->toDateString() => (int) $r->wp]);

            for ($i = 13; $i >= 0; $i--) {
                $d = $day->copy()->subDays($i);
                $trend[] = [
                    'label' => $d->format('j'),
                    'dow' => $d->format('D'),
                    'wp' => $byDay[$d->toDateString()] ?? 0,
                    'current' => $d->isSameDay($day),
                ];
            }
        }

        return view('tasksheet.index', [
            'teams' => $teams,
            'team' => $team,
            'day' => $day,
            'prev' => $day->copy()->subDay(),
            'next' => $day->copy()->addDay(),
            'isToday' => $day->isToday(),
            'isPast' => $isPast,
            'rowUsers' => $rowUsers,
            'entries' => $entries,
            'trend' => $trend,
        ]);
    }

    public function upsert(TasksheetEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $date = Carbon::parse($data['date'])->toDateString();

        // whereDate, not firstOrNew: the date cast stores a midnight timestamp,
        // so a raw where('date', 'Y-m-d') misses the row on some drivers.
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
            'plan', 'result', 'comment', 'tickets', 'work_points', 'ticket_count', 'ticket_points', 'leave_type',
        ])->all();

        // An absent member has no task content — a leave save clears the row.
        if (! empty($fields['leave_type'])) {
            $fields = ['leave_type' => $fields['leave_type']] + array_fill_keys(
                ['plan', 'result', 'comment', 'tickets', 'work_points', 'ticket_count', 'ticket_points'], null
            );
        }

        $entry->fill($fields);

        // Feedback is written exclusively by leads; a member's save never
        // touches it (so it can't be blanked or forged).
        if ($request->user()->isLead() && $request->has('feedback')) {
            $entry->feedback = $data['feedback'] ?? null;
        }

        $entry->save();

        return redirect()->route('tasksheet.index', [
            'team' => $entry->team_id,
            'date' => $date,
        ])->with('success', 'Tasksheet saved.');
    }
}
