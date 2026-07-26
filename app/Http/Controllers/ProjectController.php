<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectRequest;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
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
        // Releases with their owning team plus per-release task roll-ups so the
        // detail page can show progress bars without an N+1 of task queries.
        $project->load(['releases' => fn ($q) => $q
            ->with('team:id,name,color')
            ->withCount([
                'tasks',
                'tasks as done_tasks_count' => fn ($t) => $t->whereIn('status', Task::DONE_STATUSES),
            ])
            ->orderBy('start_date'),
        ]);

        $releases = $project->releases;

        // Recent history spanning the project itself and all of its releases.
        $activities = Activity::query()
            ->where(fn ($q) => $q
                ->whereIn('release_id', $releases->pluck('id'))
                ->orWhere(fn ($p) => $p
                    ->where('subject_type', $project->getMorphClass())
                    ->where('subject_id', $project->getKey())))
            ->with('causer')
            ->latest()
            ->limit(12)
            ->get();

        return view('projects.show', [
            'project' => $project,
            'releases' => $releases,
            'stats' => $this->analytics($project, $releases),
            'activities' => $activities,
        ]);
    }

    /**
     * Headline metrics and chart series for a single project's detail page.
     *
     * @param  Collection<int, Release>  $releases
     * @return array<string, mixed>
     */
    private function analytics(Project $project, Collection $releases): array
    {
        $releaseIds = $releases->pluck('id');
        $ongoing = $releases->whereNull('completed_at');
        $completed = $releases->whereNotNull('completed_at');

        // Task-status mix across every release in the project (single grouped query).
        $raw = Task::query()
            ->whereIn('release_id', $releaseIds)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $statusCounts = [];
        foreach (array_keys(Task::STATUSES) as $status) {
            $statusCounts[$status] = (int) ($raw[$status] ?? 0);
        }
        $taskTotal = array_sum($statusCounts);
        $doneTasks = $statusCounts['done'] + $statusCounts['archive'];

        // Open tasks already past their due date (excludes done / archived).
        $overdue = $releaseIds->isEmpty() ? 0 : Task::query()
            ->whereIn('release_id', $releaseIds)
            ->whereNotIn('status', Task::DONE_STATUSES)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        // Releases per owning team, busiest first (magnitude ranking → hbar chart).
        $byTeam = $releases
            ->groupBy('team_id')
            ->map(fn ($rs) => [
                'label' => $rs->first()->team->name,
                'value' => $rs->count(),
                'color' => $rs->first()->team->color,
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        // Release cadence: how many releases fall in each year·quarter, chronological.
        $byPeriod = $releases
            ->sortBy(fn ($r) => sprintf('%04d%d', $r->year, $r->quarter))
            ->groupBy(fn ($r) => $r->year.'-'.$r->quarter)
            ->map(fn ($rs) => [
                'label' => 'Q'.$rs->first()->quarter,
                'year' => $rs->first()->year,
                'count' => $rs->count(),
                'completed' => $rs->whereNotNull('completed_at')->count(),
                'current' => $rs->first()->year === (int) now()->year
                    && $rs->first()->quarter === (int) now()->quarter,
            ])
            ->values()
            ->all();

        return [
            'releaseTotal' => $releases->count(),
            'ongoing' => $ongoing->count(),
            'completed' => $completed->count(),
            'upcoming' => $ongoing->filter(fn ($r) => $r->start_date >= now()->startOfDay()
                && $r->start_date <= now()->addDays(30))->count(),
            'teamsInvolved' => $releases->pluck('team_id')->unique()->count(),
            'statusCounts' => $statusCounts,
            'statusLabels' => Task::STATUSES,
            'taskTotal' => $taskTotal,
            'doneTasks' => $doneTasks,
            'openTasks' => $taskTotal - $doneTasks,
            'overdue' => $overdue,
            'donePct' => $taskTotal > 0 ? (int) round($doneTasks / $taskTotal * 100) : 0,
            'completionPct' => $releases->count() > 0
                ? (int) round($completed->count() / $releases->count() * 100) : 0,
            'byTeam' => $byTeam,
            'byTeamMax' => collect($byTeam)->max('value') ?: 0,
            'byPeriod' => $byPeriod,
            'periodMax' => collect($byPeriod)->max('count') ?: 0,
            'spanStart' => $releases->min('start_date'),
            'spanEnd' => $releases->max('end_date'),
        ];
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
