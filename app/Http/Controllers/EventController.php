<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventRequest;
use App\Models\Event;
use App\Models\Release;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EventController extends Controller
{
    public function create(): View
    {
        $date = request('date');
        $start = $date ? Carbon::parse($date)->setTime(9, 0) : now()->addHour()->startOfHour();

        return view('events.create', [
            'event' => new Event(['type' => 'meeting', 'starts_at' => $start]),
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => [],
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        $event = Event::create($this->attributes($request) + ['created_by' => $request->user()->id]);
        $event->attendees()->sync($request->validated('attendees') ?? []);

        return redirect()->route('events.show', $event)->with('success', 'Event created.');
    }

    public function show(Event $event): View
    {
        $event->load(['creator', 'release', 'attendees', 'meetingNotes.author']);

        return view('events.show', compact('event'));
    }

    public function edit(Event $event): View
    {
        $this->authorize('update', $event);

        return view('events.edit', [
            'event' => $event,
            'releases' => Release::orderBy('year', 'desc')->orderBy('name')->get(),
            'users' => User::active()->orderBy('name')->get(),
            'selectedAttendees' => $event->attendees->pluck('id')->all(),
        ]);
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update($this->attributes($request));
        $event->attendees()->sync($request->validated('attendees') ?? []);

        return redirect()->route('events.show', $event)->with('success', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return redirect()->route('calendar.index')->with('success', 'Event deleted.');
    }

    /**
     * Writable attributes with all-day normalized to day bounds.
     *
     * @return array<string, mixed>
     */
    private function attributes(EventRequest $request): array
    {
        $data = $request->safe()->only([
            'title', 'description', 'type', 'starts_at', 'ends_at', 'all_day', 'location', 'release_id',
        ]);

        $start = Carbon::parse($data['starts_at']);
        $end = ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;

        if (! empty($data['all_day'])) {
            $start = $start->startOfDay();
            $end = ($end ?? $start)->endOfDay();
        }

        $data['starts_at'] = $start;
        $data['ends_at'] = $end;

        return $data;
    }
}
