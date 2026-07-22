<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamRequest;
use App\Models\Team;
use App\Services\OverlapChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $teams = Team::withCount('releases')
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();

        return view('teams.index', compact('teams'));
    }

    public function create(): View
    {
        return view('teams.create', ['team' => new Team(['color' => '#0891b2'])]);
    }

    public function store(TeamRequest $request): RedirectResponse
    {
        $team = Team::create($request->validated());

        return redirect()->route('teams.index')
            ->with('success', "Team “{$team->name}” created.");
    }

    public function show(Team $team, OverlapChecker $overlap): View
    {
        $team->load(['releases.project', 'releases.team']);
        $releases = $team->releases->sortBy('start_date')->values();
        $conflicts = $overlap->flagConflicts($releases);

        return view('teams.show', compact('team', 'releases', 'conflicts'));
    }

    public function edit(Team $team): View
    {
        return view('teams.edit', compact('team'));
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
}
