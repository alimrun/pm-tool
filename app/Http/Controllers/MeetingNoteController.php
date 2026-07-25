<?php

namespace App\Http\Controllers;

use App\Http\Requests\MeetingNoteRequest;
use App\Models\Event;
use App\Models\MeetingNote;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MeetingNoteController extends Controller
{
    public function index(): View
    {
        $filter = request('release'); // null (all) | 'general' | release id

        // Optional meeting-date span; a reversed span is swapped, not rejected.
        $from = ($f = request('from')) ? Carbon::parse($f)->toDateString() : null;
        $to = ($t = request('to')) ? Carbon::parse($t)->toDateString() : null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $notes = MeetingNote::with(['author', 'release'])
            ->withCount('attendees')
            ->visibleTo(request()->user())
            ->when($filter === 'general', fn ($q) => $q->general())
            ->when($filter && $filter !== 'general', fn ($q) => $q->forRelease((int) $filter))
            ->when($from, fn ($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('meeting_date', '<=', $to))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->get();

        return view('meeting-notes.index', [
            'notes' => $notes,
            'filter' => $filter,
            'from' => $from,
            'to' => $to,
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        // "Write meeting note" on a meeting-type event passes ?event={id}:
        // the note is pre-filled from — and linked to — that event.
        $event = ($eventId = request()->integer('event')) ? Event::find($eventId) : null;

        return view('meeting-notes.create', [
            'meetingNote' => new MeetingNote([
                'event_id' => $event?->id,
                'title' => $event?->title,
                'release_id' => $event?->release_id ?? (request()->integer('release') ?: null),
                'meeting_date' => $event?->starts_at ?? now(),
            ]),
            'releases' => Release::ongoing()->orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            // Notes written from an event start with that event's attendees.
            'selectedAttendees' => $event ? $event->attendees()->pluck('users.id')->all() : [],
        ]);
    }

    public function store(MeetingNoteRequest $request): RedirectResponse
    {
        $note = MeetingNote::create($request->safe()->merge([
            'created_by' => $request->user()->id,
        ])->only(['title', 'meeting_date', 'release_id', 'event_id', 'body', 'visibility', 'created_by']));

        $note->attendees()->sync($request->validated('attendees') ?? []);

        return redirect()->route('meeting-notes.show', $note)->with('success', 'Meeting note created.');
    }

    public function show(MeetingNote $meetingNote): View
    {
        $this->authorize('view', $meetingNote);

        $meetingNote->load(['author', 'release', 'event', 'attendees']);

        return view('meeting-notes.show', compact('meetingNote'));
    }

    public function edit(MeetingNote $meetingNote): View
    {
        $this->authorize('update', $meetingNote);

        // Ongoing releases, plus the note's own release even if it has since
        // completed — so editing never silently drops an existing link.
        $releases = Release::query()
            ->where(fn ($q) => $q->whereNull('completed_at')->orWhere('id', $meetingNote->release_id))
            ->orderBy('year', 'desc')->orderBy('name')->get();

        return view('meeting-notes.edit', [
            'meetingNote' => $meetingNote,
            'releases' => $releases,
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => $meetingNote->attendees()->pluck('users.id')->all(),
        ]);
    }

    public function update(MeetingNoteRequest $request, MeetingNote $meetingNote): RedirectResponse
    {
        $this->authorize('update', $meetingNote);

        $meetingNote->update($request->safe()->only(['title', 'meeting_date', 'release_id', 'event_id', 'body', 'visibility']));
        $meetingNote->attendees()->sync($request->validated('attendees') ?? []);

        return redirect()->route('meeting-notes.show', $meetingNote)->with('success', 'Meeting note updated.');
    }

    public function destroy(MeetingNote $meetingNote): RedirectResponse
    {
        $this->authorize('delete', $meetingNote);

        $meetingNote->delete();

        return redirect()->route('meeting-notes.index')->with('success', 'Meeting note deleted.');
    }
}
