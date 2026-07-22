<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $project->color }}"></span>
                <h2 class="page-title">{{ $project->name }}</h2>
                @if ($project->isArchived())
                    <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                @endif
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('projects.edit', $project) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">
            @if ($project->description)
                <div class="card card-pad">
                    <p class="text-sm text-slate-600">{{ $project->description }}</p>
                </div>
            @endif

            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Releases ({{ $releases->count() }})</h3>
                </div>
                @include('partials.releases-table', ['releases' => $releases, 'showProject' => false, 'showTeam' => true])
            </div>
        </div>
    </div>
</x-app-layout>
