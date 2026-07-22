<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function index(Request $request): View
    {
        // Range mode: any From/To supplied lists every visible note across the span.
        if ($request->filled('from') || $request->filled('to')) {
            return $this->rangeIndex($request);
        }

        $day = $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : now()->startOfDay();

        $notes = Note::query()
            ->with('author')
            ->whereDate('date', $day->toDateString())
            ->visibleTo($request->user())
            ->latest()
            ->get();

        return view('notes.index', [
            'isRange' => false,
            'notes' => $notes,
            'day' => $day,
            'prev' => $day->copy()->subDay(),
            'next' => $day->copy()->addDay(),
            'isToday' => $day->isToday(),
            'defaultFrom' => $day,
            'defaultTo' => $day,
        ]);
    }

    /**
     * List notes falling within the [from, to] span, grouped by day (newest first).
     */
    private function rangeIndex(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $from = ($request->filled('from') ? Carbon::parse($request->input('from')) : now())->startOfDay();
        $to = ($request->filled('to') ? Carbon::parse($request->input('to')) : now())->startOfDay();

        // Tolerate a reversed range rather than returning nothing.
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $notes = Note::query()
            ->with('author')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->visibleTo($request->user())
            ->orderByDesc('date')
            ->latest()
            ->get();

        return view('notes.index', [
            'isRange' => true,
            'grouped' => $notes->groupBy(fn (Note $note) => $note->date->toDateString()),
            'total' => $notes->count(),
            'from' => $from,
            'to' => $to,
            'defaultFrom' => $from,
            'defaultTo' => $to,
        ]);
    }

    public function store(NoteRequest $request): RedirectResponse
    {
        $request->user()->notes()->create($request->validated());

        return redirect()->route('notes.index', ['date' => $request->date])
            ->with('success', 'Note added.');
    }

    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $note->update($request->validated());

        return redirect()->route('notes.index', ['date' => $note->date->toDateString()])
            ->with('success', 'Note updated.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $date = $note->date->toDateString();
        $note->delete();

        return redirect()->route('notes.index', ['date' => $date])
            ->with('success', 'Note deleted.');
    }
}
