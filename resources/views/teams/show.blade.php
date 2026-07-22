<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $team->color }}"></span>
                <h2 class="page-title">{{ $team->name }}</h2>
                @if ($team->isArchived())
                    <span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Archived</span>
                @endif
            </div>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('teams.edit', $team) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Edit</a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">
            @php
                $conflictCount = collect($conflicts)->filter()->count();
                $busyThrough = $releases->max('end_date');
            @endphp

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="card p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Releases</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $releases->count() }}</p>
                </div>
                <div class="card p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Booked through</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $busyThrough ? $busyThrough->format('M j, Y') : '—' }}</p>
                    @if ($busyThrough)
                        <p class="text-xs text-slate-500">Next free: {{ $busyThrough->copy()->addDay()->format('M j, Y') }}</p>
                    @endif
                </div>
                <div class="rounded-xl p-5 shadow {{ $conflictCount ? 'bg-amber-50' : 'bg-white' }}">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Overlapping releases</p>
                    <p class="mt-1 text-2xl font-semibold {{ $conflictCount ? 'text-amber-700' : 'text-slate-900' }}">{{ $conflictCount }}</p>
                </div>
            </div>

            @if ($team->description)
                <div class="card card-pad">
                    <p class="text-sm text-slate-600">{{ $team->description }}</p>
                </div>
            @endif

            <div class="card overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-slate-700">Schedule (chronological)</h3>
                </div>
                @include('partials.releases-table', ['releases' => $releases, 'conflicts' => $conflicts, 'showProject' => true, 'showTeam' => false])
            </div>
        </div>
    </div>
</x-app-layout>
