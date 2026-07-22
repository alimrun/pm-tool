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
            'notes' => $notes,
            'day' => $day,
            'prev' => $day->copy()->subDay(),
            'next' => $day->copy()->addDay(),
            'isToday' => $day->isToday(),
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
