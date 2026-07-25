<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        // Optional filters: a single day, or a from/to span (reversed span swapped).
        $date = $request->filled('date') ? Carbon::parse($request->input('date'))->toDateString() : null;
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->toDateString() : null;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->toDateString() : null;
        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        $notes = Note::query()
            ->with(['author', 'recipients'])
            ->visibleTo($user)
            ->when($date, fn ($q) => $q->whereDate('date', $date))
            ->when(! $date && $from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when(! $date && $to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('notes.index', [
            'notes' => $notes,
            'date' => $date,
            'from' => $from,
            'to' => $to,
            'today' => now()->toDateString(),
            'users' => User::active()->where('id', '!=', $user->id)->orderBy('name')->get(),
        ]);
    }

    public function store(NoteRequest $request): RedirectResponse
    {
        $note = $request->user()->notes()->create($request->safe()->only(['date', 'body', 'visibility']));
        $this->syncRecipients($note, $request);

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->update($request->safe()->only(['date', 'body', 'visibility']));
        $this->syncRecipients($note, $request);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }

    /**
     * Sync the "shared with" list, but only for a specific-visibility note —
     * private/shared notes never carry recipients.
     */
    private function syncRecipients(Note $note, NoteRequest $request): void
    {
        $recipients = $note->visibility === Note::VISIBILITY_SPECIFIC
            ? ($request->validated('recipients') ?? [])
            : [];

        $note->recipients()->sync($recipients);
    }
}
