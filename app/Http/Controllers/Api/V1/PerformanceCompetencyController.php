<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PerformanceCompetencyRequest;
use App\Http\Resources\V1\PerformanceCompetencyResource;
use App\Models\PerformanceCompetency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The competency catalog, behind the `manage-competencies` gate (org-level
 * leads only — team leads evaluate, they do not reconfigure the framework).
 *
 * A competency with recorded scores is never deleted, only deactivated: its
 * key anchors historical ratings, and removing it would orphan them.
 */
class PerformanceCompetencyController extends ApiController
{
    /** The catalog is small and order matters — returned whole, unpaginated. */
    public function index(Request $request): JsonResponse
    {
        $competencies = PerformanceCompetency::ordered()
            ->withCount('scores')
            ->when($request->filled('cadence'), fn ($q) => $q->forCadence($request->input('cadence')))
            ->when($request->boolean('active_only'), fn ($q) => $q->active())
            ->get();

        return $this->ok(PerformanceCompetencyResource::collection($competencies));
    }

    public function show(PerformanceCompetency $competency): JsonResponse
    {
        return $this->ok(new PerformanceCompetencyResource($competency->loadCount('scores')));
    }

    public function store(PerformanceCompetencyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['key'] = $this->uniqueKey($data['name']);
        $data['position'] ??= (int) PerformanceCompetency::max('position') + 1;

        $competency = PerformanceCompetency::create($data);

        return $this->created(new PerformanceCompetencyResource($competency), 'Competency added.');
    }

    /** `key` is immutable — it is what historical scores are anchored to. */
    public function update(PerformanceCompetencyRequest $request, PerformanceCompetency $competency): JsonResponse
    {
        $competency->update($request->validated());

        return $this->ok(new PerformanceCompetencyResource($competency), 'Competency updated.');
    }

    /** Flip active/inactive — the safe way to retire a scored competency. */
    public function toggle(PerformanceCompetency $competency): JsonResponse
    {
        $competency->update(['active' => ! $competency->active]);

        return $this->ok(
            new PerformanceCompetencyResource($competency),
            $competency->active ? 'Competency activated.' : 'Competency deactivated.'
        );
    }

    public function destroy(PerformanceCompetency $competency): JsonResponse
    {
        abort_if(
            $competency->scores()->exists(),
            422,
            'This competency has recorded scores — deactivate it instead of deleting.'
        );

        $competency->delete();

        return $this->message('Competency deleted.');
    }

    /** A URL-safe key derived from the name, unique across the catalog. */
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
