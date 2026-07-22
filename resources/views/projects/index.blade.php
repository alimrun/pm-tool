<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Projects</h2>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('projects.create') }}"
                   class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New project
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                @if ($projects->isEmpty())
                    <div class="p-10 text-center text-gray-500">No projects yet.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-6 py-3">Project</th>
                                <th class="px-6 py-3">Releases</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($projects as $project)
                                <tr class="{{ $project->isArchived() ? 'bg-gray-50/60' : '' }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="inline-block h-3 w-3 flex-none rounded-full" style="background-color: {{ $project->color }}"></span>
                                            <div>
                                                <a href="{{ route('projects.show', $project) }}" class="font-medium text-gray-900 hover:text-indigo-600">{{ $project->name }}</a>
                                                @if ($project->description)
                                                    <p class="text-xs text-gray-500">{{ Str::limit($project->description, 80) }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600">{{ $project->releases_count }}</td>
                                    <td class="px-6 py-4">
                                        @if ($project->isArchived())
                                            <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Archived</span>
                                        @else
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Active</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('projects.show', $project) }}" class="text-gray-500 hover:text-indigo-600">View</a>
                                            @if (auth()->user()->isAdmin())
                                                <a href="{{ route('projects.edit', $project) }}" class="text-gray-500 hover:text-indigo-600">Edit</a>
                                                @if ($project->isArchived())
                                                    <form method="POST" action="{{ route('projects.restore', $project) }}">@csrf
                                                        <button class="text-gray-500 hover:text-emerald-600">Restore</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('projects.archive', $project) }}">@csrf
                                                        <button class="text-gray-500 hover:text-amber-600">Archive</button>
                                                    </form>
                                                @endif
                                                @if ($project->releases_count === 0)
                                                    <form method="POST" action="{{ route('projects.destroy', $project) }}"
                                                          onsubmit="return confirm('Delete this project permanently?')">
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
