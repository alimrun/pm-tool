<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\MeetingNoteRequest;
use App\Http\Resources\V1\MeetingNoteResource;
use App\Models\MeetingNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Meeting minutes — release-linked or general.
 *
 * Unlike personal notes, these are shared team records: a lead may delete any
 * of them (cleanup after someone leaves), but editing stays with the author.
 * Attendees-only notes are filtered at the query by `visibleTo()`.
 */
class MeetingNoteController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $filter = $request->input('release'); // null (all) | 'general' | release id

        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $query = MeetingNote::with(['author', 'release'])
            ->withCount('attendees')
            ->visibleTo($request->user())
            ->when($filter === 'general', fn ($q) => $q->general())
            ->when($filter && $filter !== 'general', fn ($q) => $q->forRelease((int) $filter))
            ->when($from, fn ($q) => $q->whereDate('meeting_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('meeting_date', '<=', $to))
            ->orderByDesc('meeting_date')
            ->orderByDesc('id');

        return $this->paginate($request, $query, MeetingNoteResource::class);
    }

    public function show(MeetingNote $meetingNote): JsonResponse
    {
        $this->authorize('view', $meetingNote);

        return $this->ok(
            new MeetingNoteResource($meetingNote->load(['author', 'release', 'event', 'attendees']))
        );
    }

    public function store(MeetingNoteRequest $request): JsonResponse
    {
        $note = MeetingNote::create($request->safe()->merge([
            'created_by' => $request->user()->id,
        ])->only(['title', 'meeting_date', 'release_id', 'event_id', 'body', 'visibility', 'created_by']));

        $note->attendees()->sync($request->validated('attendees') ?? []);

        return $this->created(
            new MeetingNoteResource($note->load(['author', 'release', 'event', 'attendees'])),
            'Meeting note created.'
        );
    }

    public function update(MeetingNoteRequest $request, MeetingNote $meetingNote): JsonResponse
    {
        $this->authorize('update', $meetingNote);

        $meetingNote->update($request->safe()->only([
            'title', 'meeting_date', 'release_id', 'event_id', 'body', 'visibility',
        ]));

        $meetingNote->attendees()->sync($request->validated('attendees') ?? []);

        return $this->ok(
            new MeetingNoteResource($meetingNote->load(['author', 'release', 'event', 'attendees'])),
            'Meeting note updated.'
        );
    }

    public function destroy(MeetingNote $meetingNote): JsonResponse
    {
        $this->authorize('delete', $meetingNote);

        $meetingNote->delete();

        return $this->message('Meeting note deleted.');
    }
}
