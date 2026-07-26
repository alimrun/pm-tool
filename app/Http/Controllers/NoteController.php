<?php

namespace App\Http\Controllers;

use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Models\User;
use App\Services\NoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NoteController extends Controller
{
    public function __construct(private readonly NoteService $notes) {}

    public function index(Request $request): View
    {
        $request->validate([
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $filters = $this->notes->normalizeFilters($request->only(['date', 'from', 'to']));

        return view('notes.index', [
            'notes' => $this->notes->visibleTo($user, $filters)->paginate(15)->withQueryString(),
            'date' => $filters['date'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'today' => now()->toDateString(),
            'users' => User::active()->where('id', '!=', $user->id)->orderBy('name')->get(),
        ]);
    }

    public function store(NoteRequest $request): RedirectResponse
    {
        $this->notes->create(
            $request->validated(),
            $request->validated('recipients') ?? [],
            $request->user(),
        );

        return redirect()->route('notes.index')->with('success', 'Note added.');
    }

    public function update(NoteRequest $request, Note $note): RedirectResponse
    {
        $this->authorize('update', $note);

        $this->notes->update($note, $request->validated(), $request->validated('recipients') ?? []);

        return back()->with('success', 'Note updated.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $this->authorize('delete', $note);

        $note->delete();

        return back()->with('success', 'Note deleted.');
    }
}
