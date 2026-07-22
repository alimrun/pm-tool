<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="page-title">Teams</h2>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('teams.create') }}"
                   class="btn-primary btn-sm">
                    New team
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container">
            <div class="card overflow-hidden">
                @if ($teams->isEmpty())
                    <div class="p-10 text-center text-slate-500">No teams yet.</div>
                @else
                    <table class="table-base">
                        <thead class="bg-slate-50 text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Team</th>
                                <th class="px-6 py-3">Releases</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($teams as $team)
                                <tr class="{{ $team->isArchived() ? 'bg-slate-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $team->color }}"></span>
                                            <div>
                                                <a href="{{ route('teams.show', $team) }}" class="font-medium text-slate-900 hover:text-indigo-600">{{ $team->name }}</a>
                                                @if ($team->description)
                                                    <p class="text-xs text-slate-500">{{ Str::limit($team->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600">{{ $team->releases_count }}</td>
                                    <td class="px-6 py-4">
                                        @if ($team->isArchived())
                                            <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('teams.show', $team) }}" class="text-slate-500 hover:text-indigo-600">View</a>
                                            @if (auth()->user()->isAdmin())
                                                <a href="{{ route('teams.edit', $team) }}" class="text-slate-500 hover:text-indigo-600">Edit</a>
                                                @if ($team->isArchived())
                                                    <form method="POST" action="{{ route('teams.restore', $team) }}">@csrf
                                                        <button class="text-slate-500 hover:text-emerald-600">Restore</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('teams.archive', $team) }}">@csrf
                                                        <button class="text-slate-500 hover:text-amber-600">Archive</button>
                                                    </form>
                                                @endif
                                                @if ($team->releases_count === 0)
                                                    <form method="POST" action="{{ route('teams.destroy', $team) }}"
                                                          data-confirm="Delete this team permanently?" data-confirm-verb="Delete">
                                                        @csrf @method('DELETE')
                                                        <button class="text-slate-500 hover:text-rose-600">Delete</button>
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
