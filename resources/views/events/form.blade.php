@php
    $selected = collect(old('attendees', $selectedAttendees ?? []))->map(fn ($i) => (int) $i)->all();
    $fmt = fn ($dt) => $dt ? \Illuminate\Support\Carbon::parse($dt)->format('Y-m-d\TH:i') : '';
@endphp
<div class="space-y-6">
    <div>
        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
        <input id="title" name="title" type="text" value="{{ old('title', $event->title) }}" required
               placeholder="e.g. Sprint planning" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('title') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach (\App\Models\Event::TYPES as $val => $label)
                    <option value="{{ $val }}" @selected(old('type', $event->type) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="all_day" value="1" @checked(old('all_day', $event->all_day))
                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                All day
            </label>
        </div>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="starts_at" class="block text-sm font-medium text-gray-700">Starts</label>
            <input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $fmt($event->starts_at)) }}" required
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('starts_at') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="ends_at" class="block text-sm font-medium text-gray-700">Ends <span class="text-gray-400">(optional)</span></label>
            <input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $fmt($event->ends_at)) }}"
                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('ends_at') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>
    <p class="-mt-3 text-xs text-gray-400">For all-day events the time is ignored — the event covers the whole day(s).</p>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <label for="location" class="block text-sm font-medium text-gray-700">Location <span class="text-gray-400">(optional)</span></label>
            <input id="location" name="location" type="text" value="{{ old('location', $event->location) }}"
                   placeholder="Room 4 / Zoom" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>
        <div>
            <label for="release_id" class="block text-sm font-medium text-gray-700">Related release <span class="text-gray-400">(optional)</span></label>
            <select id="release_id" name="release_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">— None —</option>
                @foreach ($releases as $r)
                    <option value="{{ $r->id }}" @selected((int) old('release_id', $event->release_id) === $r->id)>{{ $r->name }} ({{ $r->year }})</option>
                @endforeach
            </select>
        </div>
    </div>

    <div>
        <label for="attendees" class="block text-sm font-medium text-gray-700">Attendees <span class="text-gray-400">(optional)</span></label>
        <select id="attendees" name="attendees[]" multiple size="6"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($users as $u)
                <option value="{{ $u->id }}" @selected(in_array($u->id, $selected, true))>{{ $u->name }} ({{ $u->roleLabel() }})</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-400">Hold ⌘/Ctrl to select multiple.</p>
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-gray-400">(optional)</span></label>
        <textarea id="description" name="description" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $event->description) }}</textarea>
    </div>
</div>
