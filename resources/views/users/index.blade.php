<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Users</h2>
            <a href="{{ route('users.create') }}"
               class="btn-primary btn-sm">
                New user
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <div class="card overflow-hidden">
                <table class="table-base">
                    <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($users as $user)
                            <tr class="{{ $user->isActive() ? '' : 'bg-slate-50/60' }}">
                                <td class="px-6 py-4">
                                    <span class="font-medium text-slate-900">{{ $user->name }}</span>
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-xs text-slate-400">(you)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600">{{ $user->email }}</td>
                                <td class="px-6 py-4">@include('partials.role-badge', ['role' => $user->role])</td>
                                <td class="px-6 py-4">
                                    @if ($user->isActive())
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Deactivated</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('users.edit', $user) }}" class="text-slate-500 hover:text-indigo-600">Edit</a>
                                        <form method="POST" action="{{ route('users.toggle', $user) }}">
                                            @csrf
                                            <button class="text-slate-500 hover:{{ $user->isActive() ? 'text-amber-600' : 'text-emerald-600' }}">
                                                {{ $user->isActive() ? 'Deactivate' : 'Reactivate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                              data-confirm="Delete this user permanently?" data-confirm-verb="Delete">
                                            @csrf @method('DELETE')
                                            <button class="text-slate-500 hover:text-rose-600">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
