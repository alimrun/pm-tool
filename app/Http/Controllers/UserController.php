<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $role = $request->input('role');
        $status = $request->input('status'); // active | inactive | (all)

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role && array_key_exists($role, User::ROLES), fn ($q) => $q->where('role', $role))
            ->when($status === 'active', fn ($q) => $q->whereNull('deactivated_at'))
            ->when($status === 'inactive', fn ($q) => $q->whereNotNull('deactivated_at'))
            ->orderByRaw('deactivated_at is not null') // active first
            ->orderBy('name')
            ->get();

        // Overview reflects the whole directory, independent of the filters above.
        $everyone = User::query()->get(['id', 'role', 'deactivated_at']);
        $roleDistribution = collect(User::ROLES)
            ->map(fn ($label, $role) => [
                'role' => $role,
                'label' => $label,
                'count' => $everyone->where('role', $role)->count(),
            ])
            ->values()->all();
        $stats = [
            'total' => $everyone->count(),
            'active' => $everyone->whereNull('deactivated_at')->count(),
            'inactive' => $everyone->whereNotNull('deactivated_at')->count(),
            'engineers' => $everyone->whereIn('role', [User::ROLE_DEVELOPER, User::ROLE_QA])->count(),
        ];

        return view('users.index', [
            'users' => $users,
            'roles' => User::ROLES,
            'filters' => compact('search', 'role', 'status'),
            'stats' => $stats,
            'roleDistribution' => $roleDistribution,
        ]);
    }

    public function create(): View
    {
        return view('users.create', ['user' => new User(['role' => User::ROLE_DEVELOPER])]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        return redirect()->route('users.index')
            ->with('success', "User “{$user->name}” created.");
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Leave the password unchanged when the field is left blank.
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        // Don't let the last active admin be demoted out of the admin role.
        if ($user->isAdmin() && $data['role'] !== User::ROLE_ADMIN && $this->isLastActiveAdmin($user)) {
            return back()->with('error', 'You cannot change the role of the last active administrator.');
        }

        $user->update($data);

        return redirect()->route('users.index')
            ->with('success', "User “{$user->name}” updated.");
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->isActive()) {
            if ($this->isSelf($user)) {
                return back()->with('error', 'You cannot deactivate your own account.');
            }
            if ($this->isLastActiveAdmin($user)) {
                return back()->with('error', 'You cannot deactivate the last active administrator.');
            }

            $user->update(['deactivated_at' => now()]);

            return back()->with('success', "User “{$user->name}” deactivated.");
        }

        $user->update(['deactivated_at' => null]);

        return back()->with('success', "User “{$user->name}” reactivated.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($this->isSelf($user)) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        if ($this->isLastActiveAdmin($user)) {
            return back()->with('error', 'You cannot delete the last active administrator.');
        }

        $name = $user->name;

        // Soft delete: the account disappears from listings and can no longer
        // sign in, but everything they produced stays visible, tagged
        // "Deleted user". Their team memberships end now (left_at) so sheets
        // after this date no longer expect them.
        $user->teams->each(
            fn ($team) => $team->memberRecords()->updateExistingPivot($user->id, ['left_at' => now()])
        );
        $user->delete(); // model's deleting hook also vacates any team they lead

        return redirect()->route('users.index')
            ->with('success', "User “{$name}” deleted.");
    }

    private function isSelf(User $user): bool
    {
        return $user->id === request()->user()->id;
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return $user->isAdmin()
            && $user->isActive()
            && User::active()->where('role', User::ROLE_ADMIN)->count() <= 1;
    }
}
