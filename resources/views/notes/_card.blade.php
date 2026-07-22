{{-- A single note card. Requires $note (with author loaded). --}}
<div class="card card-pad flex flex-col" x-data="{ editing: false }">
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

    <div x-show="!editing" class="prose-notes mt-3 flex-1 text-sm text-slate-700">{!! $note->bodyHtml() !!}</div>

    @can('update', $note)
        <form x-show="editing" x-cloak method="POST" action="{{ route('notes.update', $note) }}" class="mt-3">
            @csrf @method('PUT')
            <input type="hidden" name="date" value="{{ $note->date->toDateString() }}">
            <input id="note-edit-{{ $note->id }}" type="hidden" name="body" value="{{ $note->body }}">
            <trix-editor input="note-edit-{{ $note->id }}" class="prose-notes"></trix-editor>
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
        <div x-show="!editing" class="mt-3 flex gap-3 border-t border-slate-100 pt-3">
            <button type="button" @click="editing = true" class="text-xs text-slate-400 transition hover:text-brand-600">Edit</button>
            <form method="POST" action="{{ route('notes.destroy', $note) }}" data-confirm="Delete this note?" data-confirm-verb="Delete">
                @csrf @method('DELETE')
                <button class="text-xs text-slate-400 transition hover:text-rose-600">Delete</button>
            </form>
        </div>
    @endcan
</div>
