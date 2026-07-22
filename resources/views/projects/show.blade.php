<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $project->color }}"></span>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $project->name }}</h2>
                @if ($project->isArchived())
                    <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-700">Archived</span>
                @endif
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('projects.edit', $project) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($project->description)
                <div class="rounded-xl bg-white p-6 shadow">
                    <p class="text-sm text-gray-600">{{ $project->description }}</p>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl bg-white shadow">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-700">Releases ({{ $releases->count() }})</h3>
                </div>
                @include('partials.releases-table', ['releases' => $releases, 'showProject' => false, 'showTeam' => true])
            </div>
        </div>
    </div>
</x-app-layout>
