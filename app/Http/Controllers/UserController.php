<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderByRaw('deactivated_at is not null') // active first
            ->orderBy('name')
            ->get();

        return view('users.index', compact('users'));
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
        $user->delete();

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
