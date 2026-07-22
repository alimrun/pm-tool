<div class="space-y-6">
    @if ($meetingNote->event)
        <input type="hidden" name="event_id" value="{{ old('event_id', $meetingNote->event_id) }}">
        <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
            Linked to event: <span class="font-medium text-slate-700">{{ $meetingNote->event->title }}</span>
        </p>
    @endif

    <div>
        <label for="title" class="block text-sm font-medium text-slate-700">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $meetingNote->title) }}" required
               placeholder="e.g. Sprint retro — action items" class="field-input">
        @error('title') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="meeting_date" class="block text-sm font-medium text-slate-700">Meeting date</label>
            <input id="meeting_date" name="meeting_date" type="date" required
                   value="{{ old('meeting_date', $meetingNote->meeting_date?->toDateString()) }}" class="field-input">
            @error('meeting_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="release_id" class="block text-sm font-medium text-slate-700">Related release <span class="text-slate-400">(optional)</span></label>
            <select id="release_id" name="release_id" class="field-input">
                <option value="">— General (no release) —</option>
                @foreach ($releases as $r)
                    <option value="{{ $r->id }}" @selected((int) old('release_id', $meetingNote->release_id) === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                @endforeach
            </select>
            @error('release_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="meeting-note-body" class="block text-sm font-medium text-slate-700">Notes</label>
        <input id="meeting-note-body" type="hidden" name="body" value="{{ old('body', $meetingNote->body) }}">
        <trix-editor input="meeting-note-body" placeholder="Discussion, decisions, action items…" class="prose-notes mt-1"></trix-editor>
        @error('body') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>
</div>
