<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TeamResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Team membership and lead assignment.
 *
 * Membership is never erased. Removing someone stamps `left_at` on the pivot
 * so the team's past tasksheets and performance records still know they were
 * there — deleting the row would silently rewrite history.
 */
class TeamMemberController extends ApiController
{
    /** Current members, plus the active users eligible to be added. */
    public function index(Team $team): JsonResponse
    {
        $team->load('members');

        $assignable = User::active()
            ->whereNotIn('id', $team->members->pluck('id'))
            ->orderBy('name')
            ->get();

        return $this->ok([
            'members' => UserSummaryResource::collection($team->members)->resolve(),
            'assignable_users' => UserSummaryResource::collection($assignable)->resolve(),
        ]);
    }

    /** Add a member, restoring a previous membership rather than duplicating it. */
    public function store(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $team->memberRecords()->syncWithoutDetaching([$data['user_id'] => ['left_at' => null]]);

        $user = User::find($data['user_id']);

        return $this->ok(
            new TeamResource($team->load('members')),
            "{$user->name} added to {$team->name}."
        );
    }

    /** Soft leave: stamp the departure, keep the row. */
    public function destroy(Team $team, User $user): JsonResponse
    {
        $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()]);

        return $this->ok(
            new TeamResource($team->load('members')),
            "{$user->name} removed from {$team->name}."
        );
    }

    /** Assign or clear the team lead — any active user, regardless of role. */
    public function updateLead(Request $request, Team $team): JsonResponse
    {
        $data = $request->validate([
            'team_lead_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deactivated_at')],
        ]);

        $team->update(['team_lead_id' => $data['team_lead_id'] ?? null]);
        $team->load('teamLead');

        return $this->ok(
            new TeamResource($team),
            $team->team_lead_id
                ? "{$team->teamLead->name} set as lead of {$team->name}."
                : "Team lead cleared for {$team->name}."
        );
    }
}
