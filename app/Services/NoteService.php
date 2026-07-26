<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Personal daily notes.
 *
 * Scoping happens in the query, not after it: a note the viewer may not read is
 * never loaded, so there is nothing to accidentally serialize. Recipients are
 * meaningful only on a "specific people" note — switching a note to private or
 * shared clears the list rather than leaving a stale share behind.
 */
class NoteService
{
    /** The attributes a note write accepts. */
    private const WRITABLE = ['date', 'body', 'visibility'];

    /**
     * Notes the viewer may see: everyone's shared notes, their own, and
     * "specific" notes they are a recipient of.
     *
     * @param  array{date?: ?string, from?: ?string, to?: ?string}  $filters
     * @return Builder<Note>
     */
    public function visibleTo(User $viewer, array $filters = []): Builder
    {
        $date = $filters['date'] ?? null;

        return Note::query()
            ->with(['author', 'recipients'])
            ->visibleTo($viewer)
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            // A single-date filter wins outright; a range only applies without one.
            ->when(! $date && ($filters['from'] ?? null), fn ($q) => $q->whereDate('date', '>=', $filters['from']))
            ->when(! $date && ($filters['to'] ?? null), fn ($q) => $q->whereDate('date', '<=', $filters['to']))
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $recipientIds
     */
    public function create(array $attributes, array $recipientIds, User $author): Note
    {
        $note = $author->notes()->create($this->writable($attributes));
        $this->syncRecipients($note, $recipientIds);

        return $note;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int|string>  $recipientIds
     */
    public function update(Note $note, array $attributes, array $recipientIds): Note
    {
        $note->update($this->writable($attributes));
        $this->syncRecipients($note, $recipientIds);

        return $note;
    }

    /**
     * Normalize an optional date filter to a plain Y-m-d, swapping a reversed
     * range rather than rejecting it.
     *
     * @param  array{date?: ?string, from?: ?string, to?: ?string}  $input
     * @return array{date: ?string, from: ?string, to: ?string}
     */
    public function normalizeFilters(array $input): array
    {
        $date = filled($input['date'] ?? null) ? Carbon::parse($input['date'])->toDateString() : null;
        $from = filled($input['from'] ?? null) ? Carbon::parse($input['from'])->toDateString() : null;
        $to = filled($input['to'] ?? null) ? Carbon::parse($input['to'])->toDateString() : null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['date' => $date, 'from' => $from, 'to' => $to];
    }

    /**
     * Recipients apply only to a specific-visibility note; any other visibility
     * clears them.
     *
     * @param  array<int, int|string>  $recipientIds
     */
    private function syncRecipients(Note $note, array $recipientIds): void
    {
        $note->recipients()->sync(
            $note->visibility === Note::VISIBILITY_SPECIFIC ? $recipientIds : []
        );

        $note->load('recipients');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writable(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(self::WRITABLE));
    }
}
