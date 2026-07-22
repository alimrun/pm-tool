<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::withCount('releases')
            ->orderByRaw('archived_at is not null') // active first
            ->orderBy('name')
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function create(): View
    {
        return view('projects.create', ['project' => new Project(['color' => '#4f46e5'])]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = Project::create($request->validated());

        return redirect()->route('projects.index')
            ->with('success', "Project “{$project->name}” created.");
    }

    public function show(Project $project): View
    {
        $project->load(['releases.team', 'releases.project']);
        $releases = $project->releases->sortBy('start_date')->values();

        return view('projects.show', compact('project', 'releases'));
    }

    public function edit(Project $project): View
    {
        return view('projects.edit', compact('project'));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()->route('projects.index')
            ->with('success', "Project “{$project->name}” updated.");
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->releases()->exists()) {
            return back()->with('error', 'This project has releases and cannot be deleted. Archive it instead.');
        }

        $name = $project->name;
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', "Project “{$name}” deleted.");
    }

    public function archive(Project $project): RedirectResponse
    {
        $project->update(['archived_at' => now()]);

        return back()->with('success', "Project “{$project->name}” archived.");
    }

    public function restore(Project $project): RedirectResponse
    {
        $project->update(['archived_at' => null]);

        return back()->with('success', "Project “{$project->name}” restored.");
    }
}
