<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * User administration, behind the manage-users middleware.
 *
 * The three guard rails from the web app are preserved verbatim, because they
 * protect against states the system cannot recover from: you may not lock
 * yourself out, and you may not remove or demote the last active
 * administrator. Deactivating or deleting also revokes the account's tokens,
 * so a desktop client loses access at once rather than on its next call.
 */
class UserController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $search = trim((string) $request->input('q', ''));
        $role = $request->input('role');
        $status = $request->input('status'); // active | inactive | (all)

        $query = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role && array_key_exists($role, User::ROLES), fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->whereNull('deactivated_at'))
            ->when($status === 'inactive', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->orderByRaw('deactivated_at is not null')
            ->orderBy('name');

        return $this->paginate($request, $query, UserResource::class);
    }

    /** Directory-wide totals and the role distribution, independent of filters. */
    public function stats(): JsonResponse
    {
        $everyone = User::query()->get(['id', 'role', 'deactivated_at']);

        return $this->ok([
            'total' => $everyone->count(),
            'active' => $everyone->whereNull('deactivated_at')->count(),
            'inactive' => $everyone->whereNotNull('deactivated_at')->count(),
            'engineers' => $everyone->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])->count(),
            'role_distribution' => collect(User::ROLES)
                ->map(fn ($label, $role) => [
                    'role' => $role,
                    'label' => $label,
                    'count' => $everyone->where('role', $role)->count(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return $this->ok(new UserResource($user->load('teams')));
    }

    public function store(UserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return $this->created(new UserResource($user), "User “{$user->name}” created.");
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // A blank password means "keep the current one".
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        abort_if(
            $user->isAdmin() && $data['role'] !== User::ROLE_ADMIN && $this->isLastActiveAdmin($user),
            422,
            'You cannot change the role of the last active administrator.'
        );

        $user->update($data);

        return $this->ok(new UserResource($user), "User “{$user->name}” updated.");
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($user->isActive()) {
            abort_if($this->isSelf($request, $user), 422, 'You cannot deactivate your own account.');
            abort_if($this->isLastActiveAdmin($user), 422, 'You cannot deactivate the last active administrator.');

            $user->update(['deactivated_at' => now()]);
            $user->tokens()->delete();

            return $this->ok(new UserResource($user), "User “{$user->name}” deactivated.");
        }

        $user->update(['deactivated_at' => null]);

        return $this->ok(new UserResource($user), "User “{$user->name}” reactivated.");
    }

    /**
     * Soft delete: the account can no longer sign in and drops out of
     * listings, but everything it produced stays visible, tagged "Deleted
     * user". Team memberships are stamped as ended now, so sheets after this
     * date stop expecting the person.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($this->isSelf($request, $user), 422, 'You cannot delete your own account.');
        abort_if($this->isLastActiveAdmin($user), 422, 'You cannot delete the last active administrator.');

        $name = $user->name;

        $user->teams->each(
            fn ($team) => $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()])
        );

        $user->tokens()->delete();
        $user->delete(); // the model's deleting hook also vacates any team they lead

        return $this->message("User “{$name}” deleted.");
    }

    private function isSelf(Request $request, User $user): bool
    {
        return $user->id === $request->user()->id;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->isAdmin()
            && $user->isActive()
            && User::active()->where('role', User::ROLE_ADMIN)->count() <= 1;
    }
}
