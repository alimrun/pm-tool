<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Users</h2>
            <a href="{{ route('users.create') }}"
               class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                New user
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Email</th>
                            <th class="px-6 py-3">Role</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr class="{{ $user->isActive() ? '' : 'bg-gray-50/60' }}">
                                <td class="px-6 py-4">
                                    <span class="font-medium text-gray-900">{{ $user->name }}</span>
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-xs text-gray-400">(you)</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-600">{{ $user->email }}</td>
                                <td class="px-6 py-4">@include('partials.role-badge', ['role' => $user->role])</td>
                                <td class="px-6 py-4">
                                    @if ($user->isActive())
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                    @else
                                        <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Deactivated</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('users.edit', $user) }}" class="text-gray-500 hover:text-indigo-600">Edit</a>
                                        <form method="POST" action="{{ route('users.toggle', $user) }}">
                                            @csrf
                                            <button class="text-gray-500 hover:{{ $user->isActive() ? 'text-amber-600' : 'text-emerald-600' }}">
                                                {{ $user->isActive() ? 'Deactivate' : 'Reactivate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                                              onsubmit="return confirm('Delete this user permanently?')">
                                            @csrf @method('DELETE')
                                            <button class="text-gray-500 hover:text-rose-600">Delete</button>
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
