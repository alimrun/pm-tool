<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Teams</h2>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('teams.create') }}"
                   class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New team
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                @if ($teams->isEmpty())
                    <div class="p-10 text-center text-gray-500">No teams yet.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Team</th>
                                <th class="px-6 py-3">Releases</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($teams as $team)
                                <tr class="{{ $team->isArchived() ? 'bg-gray-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $team->color }}"></span>
                                            <div>
                                                <a href="{{ route('teams.show', $team) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $team->name }}</a>
                                                @if ($team->description)
                                                    <p class="text-xs text-gray-500">{{ Str::limit($team->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">{{ $team->releases_count }}</td>
                                    <td class="px-6 py-4">
                                        @if ($team->isArchived())
                                            <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Archived</span>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('teams.show', $team) }}" class="text-gray-500 hover:text-indigo-600">View</a>
                                            @if (auth()->user()->isAdmin())
                                                <a href="{{ route('teams.edit', $team) }}" class="text-gray-500 hover:text-indigo-600">Edit</a>
                                                @if ($team->isArchived())
                                                    <form method="POST" action="{{ route('teams.restore', $team) }}">@csrf
                                                        <button class="text-gray-500 hover:text-emerald-600">Restore</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('teams.archive', $team) }}">@csrf
                                                        <button class="text-gray-500 hover:text-amber-600">Archive</button>
                                                    </form>
                                                @endif
                                                @if ($team->releases_count === 0)
                                                    <form method="POST" action="{{ route('teams.destroy', $team) }}"
                                                          onsubmit="return confirm('Delete this team permanently?')">
                                                        @csrf @method('DELETE')
                                                        <button class="text-gray-500 hover:text-rose-600">Delete</button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
