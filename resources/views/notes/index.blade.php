@php use App\Models\Note; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <h2 class="page-title">Notes</h2>

                @unless ($isRange)
                    {{-- Single-day navigation --}}
                    <div class="flex items-center gap-1">
                        <a href="{{ route('notes.index', ['date' => $prev->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Previous day">‹</a>
                        <form method="GET" action="{{ route('notes.index') }}">
                            <input type="date" name="date" value="{{ $day->toDateString() }}" onchange="this.form.submit()"
                                   class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        </form>
                        <a href="{{ route('notes.index', ['date' => $next->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Next day">›</a>
                        <a href="{{ route('notes.index') }}" class="btn-ghost btn-sm ml-1">Today</a>
                    </div>
                @endunless

                {{-- Date-range filter --}}
                <form method="GET" action="{{ route('notes.index') }}" class="flex items-center gap-1">
                    <label class="field-label !mt-0 !font-normal text-slate-500">Range</label>
                    <input type="date" name="from" value="{{ $defaultFrom->toDateString() }}" aria-label="From date"
                           class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <span class="text-slate-400">–</span>
                    <input type="date" name="to" value="{{ $defaultTo->toDateString() }}" aria-label="To date"
                           class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <button class="btn-secondary btn-sm">View</button>
                    @if ($isRange)
                        <a href="{{ route('notes.index') }}" class="btn-ghost btn-sm">Clear</a>
                    @endif
                </form>
            </div>

            @unless ($isRange)
                <span class="text-sm font-medium text-slate-600">{{ $day->format('l, M j, Y') }}{{ $isToday ? ' · Today' : '' }}</span>
            @else
                <span class="text-sm font-medium text-slate-600">
                    {{ $from->format('M j, Y') }} – {{ $to->format('M j, Y') }} · {{ $total }} {{ Str::plural('note', $total) }}
                </span>
            @endunless
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container space-y-6">
            @if ($isRange)
                {{-- Range view: notes grouped by day, newest first --}}
                @if ($total === 0)
                    <div class="card p-12 text-center text-sm text-slate-500">No notes in this date range.</div>
                @else
                    @foreach ($grouped as $date => $dayNotes)
                        @php $carbonDate = \Illuminate\Support\Carbon::parse($date); @endphp
                        <section class="space-y-4">
                            <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-2">
                                <h3 class="text-sm font-semibold text-slate-700">{{ $carbonDate->format('l, M j, Y') }}</h3>
                                <a href="{{ route('notes.index', ['date' => $date]) }}" class="text-xs text-slate-400 transition hover:text-brand-600">Open day →</a>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach ($dayNotes as $note)
                                    @include('notes._card')
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endif
            @else
                {{-- Add a note --}}
                <form method="POST" action="{{ route('notes.store') }}" class="card card-pad">
                    @csrf
                    <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                    <label class="field-label">Add a note for {{ $day->isToday() ? 'today' : $day->format('M j') }}</label>
                    <input id="note-add-body" type="hidden" name="body" value="{{ old('body') }}">
                    <trix-editor input="note-add-body" placeholder="Jot something down…" class="prose-notes"></trix-editor>
                    @error('body') <p class="field-error">{{ $message }}</p> @enderror
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <label class="field-label !mt-0 !font-normal text-slate-500">Visibility</label>
                            <select name="visibility" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="private" @selected(old('visibility') !== 'shared')>Private — only me</option>
                                <option value="shared" @selected(old('visibility') === 'shared')>Shared — everyone</option>
                            </select>
                        </div>
                        <button class="btn-primary btn-sm">Add note</button>
                    </div>
                </form>

                {{-- Notes --}}
                @if ($notes->isEmpty())
                    <div class="card p-12 text-center text-sm text-slate-500">No notes for this day yet.</div>
                @else
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($notes as $note)
                            @include('notes._card')
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
