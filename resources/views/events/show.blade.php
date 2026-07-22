<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="inline-block h-4 w-4 rounded-sm" style="background-color: {{ $event->typeColor() }}"></span>
                <h2 class="page-title">{{ $event->title }}</h2>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ $event->typeLabel() }}</span>
            </div>
            @can('update', $event)
                <div class="flex items-center gap-3">
                    <a href="{{ route('events.edit', $event) }}" class="btn-secondary btn-sm">Edit</a>
                    <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete this event?')">
                        @csrf @method('DELETE')
                        <button class="rounded-md border border-rose-200 bg-white px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Delete</button>
                    </form>
                </div>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="app-container space-y-6">
            <div class="card card-pad">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">When</dt>
                        <dd class="mt-1 text-sm text-slate-800">
                            {{ $event->starts_at->format('D, M j, Y') }}
                            @if ($event->isMultiDay()) – {{ $event->endOrStart()->format('D, M j, Y') }} @endif
                            <span class="text-slate-400">· {{ $event->timeLabel() }}</span>
                        </dd>
                    </div>
                    @if ($event->location)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Location</dt>
                            <dd class="mt-1 text-sm text-slate-800">{{ $event->location }}</dd>
                        </div>
                    @endif
                    @if ($event->release)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Release</dt>
                            <dd class="mt-1 text-sm">
                                <a href="{{ route('releases.show', $event->release) }}" class="text-indigo-600 hover:underline">{{ $event->release->name }}</a>
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Created by</dt>
                        <dd class="mt-1 text-sm text-slate-800">{{ $event->creator->name ?? 'Unknown' }}</dd>
                    </div>
                </dl>

                @if ($event->description)
                    <div class="mt-6">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Details</dt>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $event->description }}</p>
                    </div>
                @endif
            </div>

            <div class="card card-pad">
                <h3 class="text-sm font-semibold text-slate-700">Attendees ({{ $event->attendees->count() }})</h3>
                @if ($event->attendees->isEmpty())
                    <p class="mt-2 text-sm text-slate-400">No attendees added.</p>
                @else
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($event->attendees as $attendee)
                            <li class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-[10px] font-semibold text-indigo-700">
                                    {{ strtoupper(substr($attendee->name, 0, 1)) }}
                                </span>
                                {{ $attendee->name }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div>
                <a href="{{ route('calendar.index', ['year' => $event->starts_at->year, 'month' => $event->starts_at->month]) }}"
                   class="text-sm text-slate-500 hover:text-indigo-600">← Back to calendar</a>
            </div>
        </div>
    </div>
</x-app-layout>
