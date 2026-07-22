<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Tasksheet</h2>

                @if ($team)
                    {{-- Team switcher (viewer's teams listed first) --}}
                    <form method="GET" action="{{ route('tasksheet.index') }}">
                        <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                        <select name="team" onchange="this.form.submit()" aria-label="Team"
                                class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($teams as $t)
                                <option value="{{ $t->id }}" @selected($team->id === $t->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </form>

                    {{-- Day navigation --}}
                    <div class="flex items-center gap-1">
                        <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $prev->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Previous day">‹</a>
                        <form method="GET" action="{{ route('tasksheet.index') }}">
                            <input type="hidden" name="team" value="{{ $team->id }}">
                            <input type="date" name="date" value="{{ $day->toDateString() }}" onchange="this.form.submit()"
                                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </form>
                        <a href="{{ route('tasksheet.index', ['team' => $team->id, 'date' => $next->toDateString()]) }}"
                           class="btn-secondary btn-sm !px-2" aria-label="Next day">›</a>
                        <a href="{{ route('tasksheet.index', ['team' => $team->id]) }}" class="btn-ghost btn-sm ml-1">Today</a>
                    </div>
                @endif
            </div>

            <span class="text-sm font-medium text-slate-600">{{ $day->format('l, M j, Y') }}{{ $isToday ? ' · Today' : '' }}</span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container">
            @if (! $team)
                <div class="card p-12 text-center text-sm text-slate-500">No teams yet — create a team to start a tasksheet.</div>
            @else
                <div class="card overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Member</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Task Plan at Morning</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Day End Result</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Comment</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Work Points</th>
                                <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Tickets</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket Count</th>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Ticket Points</th>
                                @if (auth()->user()->isLead())
                                    <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Feedback</th>
                                @endif
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        @if ($rowUsers->isEmpty())
                            <tbody>
                                <tr>
                                    <td colspan="{{ auth()->user()->isLead() ? 10 : 9 }}" class="px-3 py-12 text-center text-sm text-slate-500">
                                        No developers or QA on this team{{ $isPast ? ' and no entries for this day' : '' }}.
                                    </td>
                                </tr>
                            </tbody>
                        @else
                            @foreach ($rowUsers as $rowUser)
                                @include('tasksheet._row', ['rowUser' => $rowUser, 'entry' => $entries->get($rowUser->id)])
                            @endforeach
                        @endif
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
