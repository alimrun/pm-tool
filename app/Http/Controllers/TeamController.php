<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\OverlapChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $teams = Team::withCount(['releases', 'members'])
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();

        return view('teams.index', compact('teams'));
    }

    public function create(): View
    {
        return view('teams.create', [
            'team' => new Team(['color' => '#0891b2']),
            'users' => $this->leadCandidates(),
        ]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->validated());

        return redirect()->route('teams.index')
            ->with('success', "Team “{$team->name}” created.");
    }

    public function show(Team $team, OverlapChecker $overlap): View
    {
        $team->load(['releases.project', 'releases.team', 'members', 'teamLead']);
        $releases = $team->releases->sortBy('start_date')->values();
        $conflicts = $overlap->flagConflicts($releases);

        // Active users not already on the team, for the "add member" picker.
        $assignableUsers = User::active()
            ->whereNotIn('id', $team->members->pluck('id'))
            ->orderBy('name')
            ->get();

        // Any active user may be picked as lead — role is irrelevant.
        $leadCandidates = $this->leadCandidates();

        return view('teams.show', compact('team', 'releases', 'conflicts', 'assignableUsers', 'leadCandidates'));
    }

    public function edit(Team $team): View
    {
        return view('teams.edit', [
            'team' => $team,
            'users' => $this->leadCandidates(),
        ]);
    }

    public function update(TeamRequest $request, Team $team): RedirectResponse
    {
        $team->update($request->validated());

        return redirect()->route('teams.index')
            ->with('success', "Team “{$team->name}” updated.");
    }

    public function destroy(Team $team): RedirectResponse
    {
        if ($team->releases()->exists()) {
            return back()->with('error', 'This team owns releases and cannot be deleted. Archive it instead.');
        }

        $name = $team->name;
        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', "Team “{$name}” deleted.");
    }

    public function archive(Team $team): RedirectResponse
    {
        $team->update(['archived_at' => now()]);

        return back()->with('success', "Team “{$team->name}” archived.");
    }

    public function restore(Team $team): RedirectResponse
    {
        $team->update(['archived_at' => null]);

        return back()->with('success', "Team “{$team->name}” restored.");
    }

    public function addMember(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        // Restores a previous membership (clears left_at) or creates a new one.
        $team->memberRecords()->syncWithoutDetaching([$data['user_id'] => ['left_at' => null]]);
        $user = User::find($data['user_id']);

        return back()->with('success', "{$user->name} added to {$team->name}.");
    }

    public function removeMember(Team $team, User $user): RedirectResponse
    {
        // Soft leave: keep the membership row so historical records (e.g. the
        // team's past tasksheets) still know this person was on the team.
        $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()]);

        return back()->with('success', "{$user->name} removed from {$team->name}.");
    }

    /** Assign (or clear) the team lead directly from the team page — any user, any role. */
    public function updateLead(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'team_lead_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $team->update(['team_lead_id' => $data['team_lead_id'] ?? null]);

        $message = $team->team_lead_id
            ? "{$team->teamLead()->first()->name} set as lead of {$team->name}."
            : "Team lead cleared for {$team->name}.";

        return back()->with('success', $message);
    }

    /** All active users, eligible to lead a team regardless of their role. */
    private function leadCandidates()
    {
        return User::active()->orderBy('name')->get();
    }
}
