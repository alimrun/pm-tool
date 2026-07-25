<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Meeting notes</h2>

                {{-- Filters: all / general / per-release + meeting-date range --}}
                <form method="GET" action="{{ route('meeting-notes.index') }}" class="flex flex-wrap items-center gap-1">
                    <label for="release" class="field-label !mt-0 !font-normal text-slate-500">Show</label>
                    <select id="release" name="release" onchange="this.form.submit()"
                            class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="" @selected(! $filter)>All meetings</option>
                        <option value="general" @selected($filter === 'general')>General (no release)</option>
                        @foreach ($releases as $r)
                            <option value="{{ $r->id }}" @selected((string) $filter === (string) $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                        @endforeach
                    </select>

                    <label class="field-label !mt-0 ml-2 !font-normal text-slate-500">Dates</label>
                    <input type="date" name="from" value="{{ $from }}" aria-label="From date"
                           class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <span class="text-slate-400">–</span>
                    <input type="date" name="to" value="{{ $to }}" aria-label="To date"
                           class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <button class="btn-secondary btn-sm">Apply</button>
                    @if ($from || $to)
                        <a href="{{ route('meeting-notes.index', array_filter(['release' => $filter])) }}" class="btn-ghost btn-sm">Clear</a>
                    @endif
                </form>
            </div>

            <a href="{{ route('meeting-notes.create', $filter && $filter !== 'general' ? ['release' => $filter] : []) }}" class="btn-primary btn-sm">
                <x-icon name="plus" class="h-4 w-4" />
                New meeting note
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-4">
            @if ($notes->isEmpty())
                <div class="card p-12 text-center text-sm text-slate-500">
                    No meeting notes yet.
                    <a href="{{ route('meeting-notes.create') }}" class="text-brand-600 hover:underline">Write the first one</a>.
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($notes as $note)
                        <a href="{{ route('meeting-notes.show', $note) }}" class="card card-pad block transition hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-sm font-semibold text-slate-800">{{ $note->title }}</h3>
                                @if ($note->release)
                                    <span class="shrink-0 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">{{ $note->release->name }}</span>
                                @else
                                    <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500">General</span>
                                @endif
                            </div>
                            <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-xs text-slate-400">
                                <span>{{ $note->meeting_date->format('D, M j, Y') }} · {{ $note->author->name ?? 'Unknown' }}</span>
                                @if ($note->attendees_count)
                                    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 font-medium text-slate-500">{{ $note->attendees_count }} {{ Str::plural('attendee', $note->attendees_count) }}</span>
                                @endif
                                @if ($note->isAttendeesOnly())
                                    <span class="rounded-full bg-amber-50 px-1.5 py-0.5 font-medium text-amber-700">Attendees only</span>
                                @endif
                            </p>
                            @unless (\App\Support\HtmlSanitizer::isEmpty($note->body))
                                <div class="prose-notes relative mt-3 max-h-28 overflow-hidden text-sm text-slate-600 [&>*:first-child]:mt-0">
                                    {!! $note->bodyPreviewHtml() !!}
                                    {{-- fade the clipped bottom edge --}}
                                    <span class="pointer-events-none absolute inset-x-0 bottom-0 h-8 bg-gradient-to-t from-white to-transparent"></span>
                                </div>
                            @endunless
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
