@php use App\Models\Note; @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-4">
                <h2 class="page-title">Notes</h2>
                <div class="flex items-center gap-1">
                    <a href="{{ route('notes.index', ['date' => $prev->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Previous day">‹</a>
                    <form method="GET" action="{{ route('notes.index') }}">
                        <input type="date" name="date" value="{{ $day->toDateString() }}" onchange="this.form.submit()"
                               class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    </form>
                    <a href="{{ route('notes.index', ['date' => $next->toDateString()]) }}" class="btn-secondary btn-sm !px-2" aria-label="Next day">›</a>
                    <a href="{{ route('notes.index') }}" class="btn-ghost btn-sm ml-1">Today</a>
                </div>
            </div>
            <span class="text-sm font-medium text-slate-600">{{ $day->format('l, M j, Y') }}{{ $isToday ? ' · Today' : '' }}</span>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="app-container max-w-3xl space-y-6">
            {{-- Add a note --}}
            <form method="POST" action="{{ route('notes.store') }}" class="card card-pad">
                @csrf
                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                <label for="body" class="field-label">Add a note for {{ $day->isToday() ? 'today' : $day->format('M j') }}</label>
                <textarea id="body" name="body" rows="3" required placeholder="Jot something down…" class="field-textarea">{{ old('body') }}</textarea>
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
                <div class="card p-10 text-center text-sm text-slate-500">No notes for this day yet.</div>
            @else
                <div class="space-y-3">
                    @foreach ($notes as $note)
                        <div class="card card-pad" x-data="{ editing: false }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">
                                        {{ strtoupper(substr($note->author->name ?? '?', 0, 1)) }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-slate-800">{{ $note->author->name ?? 'Unknown' }}</p>
                                        <p class="text-xs text-slate-400">{{ $note->created_at->format('g:i a') }}</p>
                                    </div>
                                </div>
                                @if ($note->isShared())
                                    <span class="badge bg-cyan-50 text-cyan-700">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path d="M13 4.5a2.5 2.5 0 11.7 1.74l-4.9 2.45a2.5 2.5 0 010 2.62l4.9 2.45a2.5 2.5 0 11-.67 1.34l-4.9-2.45a2.5 2.5 0 110-5.3l4.9-2.45A2.5 2.5 0 0113 4.5z"/></svg>
                                        Shared
                                    </span>
                                @else
                                    <span class="badge bg-slate-100 text-slate-600">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd"/></svg>
                                        Private
                                    </span>
                                @endif
                            </div>

                            <div x-show="!editing" class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $note->body }}</div>

                            @can('update', $note)
                                <form x-show="editing" x-cloak method="POST" action="{{ route('notes.update', $note) }}" class="mt-3">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="date" value="{{ $note->date->toDateString() }}">
                                    <textarea name="body" rows="3" required class="field-textarea">{{ $note->body }}</textarea>
                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                        <select name="visibility" class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500">
                                            <option value="private" @selected(! $note->isShared())>Private — only me</option>
                                            <option value="shared" @selected($note->isShared())>Shared — everyone</option>
                                        </select>
                                        <div class="flex gap-2">
                                            <button class="btn-primary btn-sm">Save</button>
                                            <button type="button" @click="editing = false" class="btn-ghost btn-sm">Cancel</button>
                                        </div>
                                    </div>
                                </form>
                                <div x-show="!editing" class="mt-2 flex gap-3">
                                    <button type="button" @click="editing = true" class="text-xs text-slate-400 transition hover:text-brand-600">Edit</button>
                                    <form method="POST" action="{{ route('notes.destroy', $note) }}" data-confirm="Delete this note?" data-confirm-verb="Delete">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-slate-400 transition hover:text-rose-600">Delete</button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
