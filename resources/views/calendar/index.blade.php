<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Calendar</h2>
                <div class="flex items-center gap-1">
                    <a href="{{ route('calendar.index', ['year' => $prev->year, 'month' => $prev->month]) }}"
                       class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-600 hover:bg-gray-50" title="Previous month">‹</a>
                    <span class="min-w-[9rem] text-center text-sm font-medium text-gray-700">{{ $monthLabel }}</span>
                    <a href="{{ route('calendar.index', ['year' => $next->year, 'month' => $next->month]) }}"
                       class="rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-600 hover:bg-gray-50" title="Next month">›</a>
                    <a href="{{ route('calendar.index') }}" class="ml-1 rounded-md border border-gray-300 bg-white px-2 py-1 text-sm text-gray-600 hover:bg-gray-50">Today</a>
                </div>
            </div>
            <a href="{{ route('events.create') }}"
               class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                New event
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl bg-white shadow">
                {{-- Weekday header --}}
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50 text-center text-xs font-medium uppercase tracking-wide text-gray-500">
                    @foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)
                        <div class="px-2 py-2">{{ $d }}</div>
                    @endforeach
                </div>

                {{-- Weeks --}}
                <div class="divide-y divide-gray-200">
                    @foreach ($weeks as $week)
                        <div class="grid grid-cols-7 divide-x divide-gray-200">
                            @foreach ($week as $day)
                                <div class="group relative min-h-[7rem] p-1.5 {{ $day['inMonth'] ? 'bg-white' : 'bg-gray-50/60' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs
                                            {{ $day['isToday'] ? 'bg-indigo-600 font-semibold text-white' : ($day['inMonth'] ? 'text-gray-700' : 'text-gray-400') }}">
                                            {{ $day['date']->day }}
                                        </span>
                                        <a href="{{ route('events.create', ['date' => $day['date']->toDateString()]) }}"
                                           class="hidden text-xs text-gray-300 hover:text-indigo-600 group-hover:block" title="Add event">＋</a>
                                    </div>

                                    <div class="mt-1 space-y-1">
                                        @foreach (collect($day['events'])->take(3) as $event)
                                            <a href="{{ route('events.show', $event) }}"
                                               class="block truncate rounded px-1 py-0.5 text-[11px] text-gray-700 hover:bg-gray-100"
                                               style="border-left: 3px solid {{ $event->typeColor() }}"
                                               title="{{ $event->title }} · {{ $event->timeLabel() }}">
                                                @unless ($event->all_day)<span class="text-gray-400">{{ $event->starts_at->format('ga') }}</span> @endunless{{ $event->title }}
                                            </a>
                                        @endforeach
                                        @if (count($day['events']) > 3)
                                            <span class="block px-1 text-[11px] text-gray-400">+{{ count($day['events']) - 3 }} more</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Legend --}}
            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-600">
                <span class="font-medium text-gray-500">Types:</span>
                @foreach (\App\Models\Event::TYPES as $key => $label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-sm" style="background-color: {{ \App\Models\Event::TYPE_COLORS[$key] }}"></span>{{ $label }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
