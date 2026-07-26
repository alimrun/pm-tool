<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\TeamRequest;
use App\Http\Resources\V1\ReleaseSummaryResource;
use App\Http\Resources\V1\TeamResource;
use App\Models\Team;
use App\Services\OverlapChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Team::with('teamLead')
            ->withCount(['releases', 'members'])
            ->when($request->filled('status'), fn ($q) => $request->input('status') === 'archived'
                ? $q->whereNotNull('archived_at')
                : $q->whereNull('archived_at'))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderByRaw('archived_at is not null')
            ->orderBy('name');

        return $this->paginate($request, $query, TeamResource::class);
    }

    /**
     * A team with its members, its lead, and its schedule — including which of
     * its releases collide, since a double-booked team is the whole point of
     * the conflict rule and a client should not have to recompute it.
     */
    public function show(Team $team, OverlapChecker $overlap): JsonResponse
    {
        $team->load(['members', 'teamLead', 'releases.project', 'releases.team'])
            ->loadCount(['releases', 'members']);

        $releases = $team->releases->sortBy('start_date')->values();
        $conflicts = $overlap->flagConflicts($releases);

        return $this->ok(
            (new TeamResource($team))->additional([
                'releases' => ReleaseSummaryResource::collection($releases),
                'conflicting_release_ids' => array_map('intval', array_keys($conflicts)),
            ])
        );
    }

    public function store(TeamRequest $request): JsonResponse
    {
        $team = Team::create($request->validated());

        return $this->created(
            new TeamResource($team->load('teamLead')->loadCount(['releases', 'members'])),
            "Team “{$team->name}” created."
        );
    }

    public function update(TeamRequest $request, Team $team): JsonResponse
    {
        $team->update($request->validated());

        return $this->ok(
            new TeamResource($team->load('teamLead')->loadCount(['releases', 'members'])),
            "Team “{$team->name}” updated."
        );
    }

    /** A team that owns releases is archived, never hard-deleted. */
    public function destroy(Team $team): JsonResponse
    {
        abort_if(
            $team->releases()->exists(),
            422,
            'This team owns releases and cannot be deleted. Archive it instead.'
        );

        $name = $team->name;
        $team->delete();

        return $this->message("Team “{$name}” deleted.");
    }

    public function archive(Team $team): JsonResponse
    {
        $team->update(['archived_at' => now()]);

        return $this->ok(new TeamResource($team), "Team “{$team->name}” archived.");
    }

    public function restore(Team $team): JsonResponse
    {
        $team->update(['archived_at' => null]);

        return $this->ok(new TeamResource($team), "Team “{$team->name}” restored.");
    }
}
