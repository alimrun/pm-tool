<?php

namespace App\Http\Controllers;

use App\Http\Requests\TasksheetEntryRequest;
use App\Models\User;
use App\Services\TasksheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TasksheetController extends Controller
{
    public function __construct(private readonly TasksheetService $tasksheet) {}

    public function index(): View
    {
        $viewer = request()->user();

        $teams = $this->tasksheet->teamsFor($viewer);
        $team = $this->tasksheet->resolveTeam($teams, request()->integer('team') ?: null);

        $day = ($d = request('date')) ? Carbon::parse($d)->startOfDay() : today();

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

        return view('tasksheet.index', [
            'teams' => $teams,
            'team' => $team,
            'day' => $day,
            'prev' => $day->copy()->subDay(),
            'next' => $day->copy()->addDay(),
            'isToday' => $day->isToday(),
            'isPast' => $day->lt(today()),
            'rowUsers' => $rowUsers,
            'entries' => $entries,
            'viewerIsMember' => $viewerIsMember,
            'trend' => $trend,
        ]);
    }

    /** Per-user tasksheet history, filterable by team and date range. */
    public function user(User $member): View
    {
        $viewer = request()->user();
        abort_unless($viewer->isLead() || $viewer->id === $member->id, 403);

        $teamFilter = request()->integer('team') ?: null;
        $from = ($f = request('from')) ? Carbon::parse($f)->toDateString() : null;
        $to = ($t = request('to')) ? Carbon::parse($t)->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return view('tasksheet.user', [
            'member' => $member,
            'entries' => $this->tasksheet->history($member, $teamFilter, $from, $to)->get(),
            'teams' => $this->tasksheet->teamsWithHistory($member),
            'teamFilter' => $teamFilter,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function upsert(TasksheetEntryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $entry = $this->tasksheet->resolveEntry($data);
        $this->authorize('update', $entry);

        $this->tasksheet->save($entry, $data, $request->user(), $request->has('feedback'));

        return redirect()->route('tasksheet.index', [
            'team' => $entry->team_id,
            'date' => $entry->date->toDateString(),
        ])->with('success', 'Tasksheet saved.');
    }
}
