<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Release timeline</h2>
            @if (auth()->user()->isAdmin())
                <a href="{{ route('releases.create') }}"
                   class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    New release
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" action="{{ route('dashboard') }}" class="rounded-xl bg-white p-4 shadow">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Year</label>
                        <select name="year" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($filters['year'] === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Quarter</label>
                        <select name="quarter" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All quarters</option>
                            @foreach ([1,2,3,4] as $q)
                                <option value="{{ $q }}" @selected($filters['quarter'] === $q)>Q{{ $q }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Project</label>
                        <select name="project_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All projects</option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected($filters['project_id'] === $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Team</label>
                        <select name="team_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All teams</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}" @selected($filters['team_id'] === $team->id)>{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500">Group by</label>
                        <select name="group_by" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="team" @selected($filters['group_by'] === 'team')>Team</option>
                            <option value="project" @selected($filters['group_by'] === 'project')>Project</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button class="w-full rounded-md bg-gray-800 px-3 py-2 text-sm font-medium text-white hover:bg-gray-900">Apply</button>
                        <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">Reset</a>
                    </div>
                </div>
            </form>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-4 text-xs text-gray-600">
                <span class="font-medium text-gray-500">Phases:</span>
                @foreach ($phaseLabels as $key => $label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-sm" style="background-color: {{ $phaseColors[$key] }}"></span>{{ $label }}
                    </span>
                @endforeach
                @if ($hasConflicts)
                    <span class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 font-medium text-amber-800">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.515 2.625H3.72c-1.344 0-2.187-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
                        Amber outline = same team double-booked
                    </span>
                @endif
            </div>

            {{-- Timeline --}}
            @if (empty($groups))
                <div class="rounded-xl bg-white p-12 text-center text-gray-500 shadow">
                    No releases match this view. @if (auth()->user()->isAdmin())<a href="{{ route('releases.create') }}" class="text-indigo-600 hover:underline">Create one</a>.@endif
                </div>
            @else
                <div class="overflow-x-auto rounded-xl bg-white shadow">
                    <div class="min-w-[900px]">
                        {{-- Month axis --}}
                        <div class="flex border-b border-gray-200 bg-gray-50">
                            <div class="w-48 flex-none px-4 py-2 text-xs font-medium uppercase tracking-wide text-gray-500">
                                {{ $filters['group_by'] === 'project' ? 'Project' : 'Team' }}
                            </div>
                            <div class="relative h-8 flex-1">
                                @foreach ($months as $m)
                                    <div class="absolute top-0 h-8 border-l border-gray-200 px-1 text-[11px] leading-8 text-gray-400"
                                         style="left: {{ $m['offset'] }}%; width: {{ $m['width'] }}%">
                                        {{ $m['label'] }}
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Rows --}}
                        @foreach ($groups as $group)
                            <div class="flex border-b border-gray-100 last:border-b-0">
                                <div class="w-48 flex-none px-4 py-4">
                                    <a href="{{ $group['type'] === 'project' ? route('projects.show', $group['id']) : route('teams.show', $group['id']) }}"
                                       class="flex items-center gap-2 text-sm font-medium text-gray-800 hover:text-indigo-600">
                                        <span class="h-3 w-3 flex-none rounded-full" style="background-color: {{ $group['color'] }}"></span>
                                        <span class="truncate">{{ $group['label'] }}</span>
                                    </a>
                                    <p class="mt-0.5 text-xs text-gray-400">{{ count($group['bars']) }} release{{ count($group['bars']) === 1 ? '' : 's' }}</p>
                                </div>

                                <div class="relative flex-1 py-3 pr-4">
                                    {{-- faint month gridlines --}}
                                    @foreach ($months as $m)
                                        <div class="pointer-events-none absolute inset-y-0 border-l border-gray-100" style="left: {{ $m['offset'] }}%"></div>
                                    @endforeach

                                    <div class="space-y-2">
                                        @foreach ($group['bars'] as $bar)
                                            @php $release = $bar['release']; @endphp
                                            <div class="relative h-8">
                                                <a href="{{ route('releases.show', $release) }}"
                                                   class="group absolute top-0 flex h-8 items-center overflow-hidden rounded-md shadow-sm ring-1 ring-inset {{ $bar['conflict'] ? 'ring-2 ring-amber-500' : 'ring-black/5' }}"
                                                   style="left: {{ $bar['offset'] }}%; width: {{ max($bar['width'], 2) }}%; background-color: #f1f5f9"
                                                   title="{{ $release->name }} · {{ $release->start_date->format('M j') }}–{{ $release->end_date->format('M j, Y') }}{{ $bar['conflict'] ? ' · OVERLAP' : '' }}">
                                                    {{-- phase segments fill the bar --}}
                                                    @foreach ($bar['phases'] as $phase)
                                                        <span class="absolute top-0 h-8"
                                                              style="left: {{ $phase['offset'] }}%; width: {{ $phase['width'] }}%; background-color: {{ $phase['color'] }}"
                                                              title="{{ $phase['label'] }}: {{ $phase['start']->format('M j') }}–{{ $phase['end']->format('M j') }}"></span>
                                                    @endforeach
                                                    <span class="relative z-10 truncate px-2 text-[11px] font-medium text-white drop-shadow-sm">
                                                        {{ $release->name }}
                                                    </span>
                                                    @if ($bar['conflict'])
                                                        <span class="relative z-10 ml-auto mr-1 flex-none rounded-full bg-white/90 px-1 text-[10px] font-bold text-amber-700">!</span>
                                                    @endif
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
