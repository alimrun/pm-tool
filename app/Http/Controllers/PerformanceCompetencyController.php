<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerformanceCompetencyRequest;
use App\Models\PerformanceCompetency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Competency-catalog management. Every action is behind the
 * `can:manage-competencies` gate (org-level leads only).
 */
class PerformanceCompetencyController extends Controller
{
    public function index(): View
    {
        return view('performance.competencies.index', [
            'competencies' => PerformanceCompetency::ordered()->withCount('scores')->get(),
            'categories' => PerformanceCompetency::CATEGORIES,
        ]);
    }

    public function create(): View
    {
        return view('performance.competencies.create', [
            'competency' => new PerformanceCompetency([
                'category' => PerformanceCompetency::CATEGORY_TECHNICAL,
                'role_scope' => PerformanceCompetency::SCOPE_BOTH,
                'cadence' => PerformanceCompetency::CADENCE_WEEKLY,
                'weight' => 1,
                'active' => true,
            ]),
        ]);
    }

    public function store(PerformanceCompetencyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['key'] = $this->uniqueKey($data['name']);
        $data['position'] ??= (int) PerformanceCompetency::max('position') + 1;

        PerformanceCompetency::create($data);

        return redirect()->route('performance.competencies.index')
            ->with('success', 'Competency added.');
    }

    public function edit(PerformanceCompetency $competency): View
    {
        return view('performance.competencies.edit', ['competency' => $competency]);
    }

    public function update(PerformanceCompetencyRequest $request, PerformanceCompetency $competency): RedirectResponse
    {
        // `key` is immutable — it anchors historical scores.
        $competency->update($request->validated());

        return redirect()->route('performance.competencies.index')
            ->with('success', 'Competency updated.');
    }

    /** Flip active/inactive — the safe way to retire a scored competency. */
    public function toggle(PerformanceCompetency $competency): RedirectResponse
    {
        $competency->update(['active' => ! $competency->active]);

        return redirect()->route('performance.competencies.index')
            ->with('success', $competency->active ? 'Competency activated.' : 'Competency deactivated.');
    }

    public function destroy(PerformanceCompetency $competency): RedirectResponse
    {
        // Preserve history: a scored competency can only be deactivated, not deleted.
        if ($competency->scores()->exists()) {
            return redirect()->route('performance.competencies.index')
                ->with('error', 'This competency has recorded scores — deactivate it instead of deleting.');
        }

        $competency->delete();

        return redirect()->route('performance.competencies.index')
            ->with('success', 'Competency deleted.');
    }

    /** A URL-safe, unique key derived from the name. */
    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'competency';
        $key = $base;
        $i = 2;
        while (PerformanceCompetency::where('key', $key)->exists()) {
            $key = $base.'-'.$i++;
        }

        return $key;
    }
}
