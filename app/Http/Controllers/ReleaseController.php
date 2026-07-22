<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseRequest;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Release;
use App\Models\Team;
use App\Models\User;
use App\Services\OverlapChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    public function __construct(private readonly OverlapChecker $overlap)
    {
    }

    public function create(): View
    {
        return view('releases.create', [
            'release' => new Release([
                'year' => (int) now()->year,
                'quarter' => (int) ceil(now()->month / 3),
            ]),
            'projects' => Project::active()->orderBy('name')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
            'phaseValues' => [],
        ]);
    }

    public function store(ReleaseRequest $request): RedirectResponse
    {
        $release = DB::transaction(function () use ($request) {
            $release = Release::create($request->safe()->only([
                'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
            ]));
            $this->syncPhases($release, $request->input('phases', []));

            return $release;
        });

        return redirect()->route('releases.show', $release)
            ->with('success', "Release “{$release->name}” created.")
            ->with($this->overlapSession($release));
    }

    public function show(Release $release): View
    {
        $release->load([
            'project', 'team', 'phases', 'documents.uploader',
            'rootTasks.subtasks.assignee', 'rootTasks.assignee', 'rootTasks.comments',
            'offDays', 'comments.user',
        ]);

        $conflicts = $this->overlap->conflictsFor(
            $release->team_id,
            $release->start_date->toDateString(),
            $release->end_date->toDateString(),
            $release->id
        );

        $history = Activity::where('release_id', $release->id)
            ->with('causer')
            ->latest()
            ->limit(40)
            ->get();

        return view('releases.show', [
            'release' => $release,
            'conflicts' => $conflicts,
            'history' => $history,
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function edit(Release $release): View
    {
        $release->load('phases');

        return view('releases.edit', [
            'release' => $release,
            'projects' => Project::active()->orderBy('name')->get(),
            'teams' => Team::active()->orderBy('name')->get(),
            'phaseValues' => $release->phases->keyBy('phase'),
        ]);
    }

    public function update(ReleaseRequest $request, Release $release): RedirectResponse
    {
        DB::transaction(function () use ($request, $release) {
            $release->update($request->safe()->only([
                'project_id', 'team_id', 'name', 'description', 'year', 'quarter', 'start_date', 'end_date',
            ]));
            $this->syncPhases($release, $request->input('phases', []));
        });

        return redirect()->route('releases.show', $release)
            ->with('success', "Release “{$release->name}” updated.")
            ->with($this->overlapSession($release->refresh()));
    }

    public function destroy(Release $release): RedirectResponse
    {
        $name = $release->name;
        $release->delete(); // cascades to phases + documents; model event removes files

        return redirect()->route('dashboard')
            ->with('success', "Release “{$name}” deleted.");
    }

    /**
     * Recreate the four canonical phases in order from the submitted date ranges.
     */
    private function syncPhases(Release $release, array $phases): void
    {
        $release->phases()->delete();

        $position = 0;
        foreach (array_keys(Release::PHASES) as $key) {
            $release->phases()->create([
                'phase' => $key,
                'position' => $position++,
                'start_date' => $phases[$key]['start'] ?? $release->start_date,
                'end_date' => $phases[$key]['end'] ?? $release->end_date,
            ]);
        }
    }

    /**
     * Flash payload for the overlap warning: an array with the warning message,
     * or an empty array when there is no conflict (so the key stays absent).
     *
     * @return array<string, string>
     */
    private function overlapSession(Release $release): array
    {
        $warning = $this->overlapWarning($release);

        return $warning ? ['overlap_warning' => $warning] : [];
    }

    /**
     * Build a human warning if this release overlaps other releases for its team.
     * Returns null when there is no conflict.
     */
    private function overlapWarning(Release $release): ?string
    {
        $conflicts = $this->overlap->conflictsFor(
            $release->team_id,
            $release->start_date->toDateString(),
            $release->end_date->toDateString(),
            $release->id
        );

        if ($conflicts->isEmpty()) {
            return null;
        }

        $list = $conflicts->map(function (Release $c) {
            return sprintf(
                '“%s” (%s – %s)',
                $c->name,
                $c->start_date->format('M j'),
                $c->end_date->format('M j, Y')
            );
        })->implode('; ');

        return sprintf(
            'Heads up: team %s is already booked during this window by %s. The release was saved anyway.',
            $release->team->name,
            $list
        );
    }
}
