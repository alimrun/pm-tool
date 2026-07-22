<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);
        $month = min(max($month, 1), 12);

        $first = Carbon::create($year, $month, 1)->startOfDay();
        $gridStart = $first->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $events = Event::query()
            ->with(['release', 'creator'])
            ->whereDate('starts_at', '<=', $gridEnd->toDateString())
            ->where(function ($q) use ($gridStart) {
                $q->whereDate('ends_at', '>=', $gridStart->toDateString())
                    ->orWhere(function ($q2) use ($gridStart) {
                        $q2->whereNull('ends_at')->whereDate('starts_at', '>=', $gridStart->toDateString());
                    });
            })
            ->orderBy('starts_at')
            ->get();

        // date (Y-m-d) => [events]
        $byDate = [];
        foreach ($events as $event) {
            foreach ($event->coveredDates($gridStart, $gridEnd) as $date) {
                $byDate[$date][] = $event;
            }
        }

        // Build the grid of weeks → days.
        $weeks = [];
        $cursor = $gridStart->copy();
        $today = now()->toDateString();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => (int) $cursor->month === $month,
                    'isToday' => $date === $today,
                    'events' => $byDate[$date] ?? [],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return view('calendar.index', [
            'weeks' => $weeks,
            'monthLabel' => $first->format('F Y'),
            'prev' => $first->copy()->subMonthNoOverflow(),
            'next' => $first->copy()->addMonthNoOverflow(),
            'today' => now(),
        ]);
    }
}
