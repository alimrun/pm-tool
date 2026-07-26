<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\EventRequest;
use App\Http\Resources\V1\EventResource;
use App\Http\Resources\V1\MeetingNoteResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Calendar events. Any signed-in user creates; EventPolicy limits editing and
 * deleting to the creator or a lead.
 */
class EventController extends ApiController
{
    /**
     * Events overlapping a window.
     *
     * The default window is the month grid a client would draw — the whole
     * weeks spanning the month, not just its first-to-last day — so events
     * that spill into the leading and trailing days are present rather than
     * appearing to vanish at the grid edges. An explicit `from`/`to` overrides
     * it.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        [$from, $to] = $this->window($request);

        $events = Event::query()
            ->with(['release', 'creator', 'attendees'])
            ->whereDate('starts_at', '<=', $to->toDateString())
            ->where(fn ($q) => $q
                ->whereDate('ends_at', '>=', $from->toDateString())
                ->orWhere(fn ($q2) => $q2
                    ->whereNull('ends_at')
                    ->whereDate('starts_at', '>=', $from->toDateString())))
            ->when($this->filterId($request, 'release_id'), fn ($q, $id) => $q->where('release_id', $id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderBy('starts_at')
            ->get();

        // Y-m-d => [event ids], so a client can fill a month grid without
        // re-deriving which days each multi-day event covers.
        $byDate = [];
        foreach ($events as $event) {
            foreach ($event->coveredDates($from, $to) as $date) {
                $byDate[$date][] = $event->id;
            }
        }

        return $this->ok([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => EventResource::collection($events)->resolve($request),
            'events_by_date' => $byDate,
        ]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $event->load(['creator', 'release', 'attendees']);

        // Attendees-only meeting notes are filtered out for viewers who may
        // not read them.
        $notes = $event->meetingNotes()->with('author')->visibleTo($request->user())->get();

        return $this->ok(
            (new EventResource($event))->additional([
                'meeting_notes' => MeetingNoteResource::collection($notes)->resolve($request),
            ])
        );
    }

    public function store(EventRequest $request): JsonResponse
    {
        $event = Event::create($this->attributes($request) + ['created_by' => $request->user()->id]);
        $event->attendees()->sync($request->validated('attendees') ?? []);

        return $this->created(
            new EventResource($event->load(['creator', 'release', 'attendees'])),
            'Event created.'
        );
    }

    public function update(EventRequest $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $event->update($this->attributes($request));
        $event->attendees()->sync($request->validated('attendees') ?? []);

        return $this->ok(
            new EventResource($event->load(['creator', 'release', 'attendees'])),
            'Event updated.'
        );
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->authorize('delete', $event);

        $event->delete();

        return $this->message('Event deleted.');
    }

    /**
     * The query window: an explicit from/to, or the full weeks spanning the
     * requested (default current) month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Request $request): array
    {
        if ($request->filled('from') || $request->filled('to')) {
            $from = Carbon::parse($request->input('from', $request->input('to')))->startOfDay();
            $to = Carbon::parse($request->input('to', $request->input('from')))->endOfDay();

            return $from->gt($to) ? [$to, $from] : [$from, $to];
        }

        $year = (int) $request->integer('year', (int) now()->year);
        $month = min(max((int) $request->integer('month', (int) now()->month), 1), 12);
        $first = Carbon::create($year, $month, 1)->startOfDay();

        return [
            $first->copy()->startOfWeek(Carbon::SUNDAY),
            $first->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY),
        ];
    }

    /**
     * Writable attributes, with an all-day event normalized to day bounds so
     * "all day" means the same span however the client sent the times.
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
