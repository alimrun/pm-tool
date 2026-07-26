<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\NoteRequest;
use App\Http\Resources\V1\NoteResource;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Personal daily notes.
 *
 * The collection is scoped with `visibleTo()` at the query, so a note the
 * caller may not read is never loaded — not merely hidden afterwards. Notes are
 * personal even from leads: NotePolicy lets only the author edit or delete one.
 */
class NoteController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : null;
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $query = Note::query()
            ->with(['author', 'recipients'])
            ->visibleTo($request->user())
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            ->when(! $date && $from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when(! $date && $to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id');

        return $this->paginate($request, $query, NoteResource::class);
    }

    public function show(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->isVisibleTo($request->user()), 404);

        return $this->ok(new NoteResource($note->load(['author', 'recipients'])));
    }

    public function store(NoteRequest $request): JsonResponse
    {
        $note = $request->user()->notes()->create($request->safe()->only(['date', 'body', 'visibility']));
        $this->syncRecipients($note, $request);

        return $this->created(new NoteResource($note->load(['author', 'recipients'])), 'Note added.');
    }

    public function update(NoteRequest $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);

        $note->update($request->safe()->only(['date', 'body', 'visibility']));
        $this->syncRecipients($note, $request);

        return $this->ok(new NoteResource($note->load(['author', 'recipients'])), 'Note updated.');
    }

    public function destroy(Note $note): JsonResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return $this->message('Note deleted.');
    }

    /**
     * Recipients only mean anything on a "specific people" note — a private or
     * shared note is cleared of them, so switching visibility cannot leave a
     * stale share list behind.
     */
    private function syncRecipients(Note $note, NoteRequest $request): void
    {
        $recipients = $note->visibility === Note::VISIBILITY_SPECIFIC
            ? ($request->validated('recipients') ?? [])
            : [];

        $note->recipients()->sync($recipients);
        $note->load('recipients');
    }
}
