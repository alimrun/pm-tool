<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $causerId = $request->filled('causer_id') ? (int) $request->integer('causer_id') : null;
        $event = $request->input('event');
        $validEvent = in_array($event, ['created', 'updated', 'deleted'], true);

        // Base query scoped to the selected user. The KPI cards and every chart
        // follow this "focus"; the event filter only narrows the feed itself so
        // the breakdown charts stay meaningful.
        $base = fn () => Activity::query()
            ->when($causerId, fn ($q) => $q->where('causer_id', $causerId));

        // ---- Paginated feed (respects both filters) ------------------------
        $activities = $base()
            ->with('causer')
            ->when($validEvent, fn ($q) => $q->where('event', $event))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // ---- KPI totals ----------------------------------------------------
        $eventCounts = $base()
            ->select('event', DB::raw('count(*) as aggregate'))
            ->groupBy('event')
            ->pluck('aggregate', 'event');

        $created = (int) ($eventCounts['created'] ?? 0);
        $updated = (int) ($eventCounts['updated'] ?? 0);
        $deleted = (int) ($eventCounts['deleted'] ?? 0);
        $total = $created + $updated + $deleted;

        $todayCount = (int) $base()->whereDate('created_at', Carbon::today())->count();
        $weekCount = (int) $base()->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())->count();
        $contributors = (int) $base()->whereNotNull('causer_id')->distinct()->count('causer_id');

        // ---- 14-day trend (fill gaps with zeroes) --------------------------
        $since = Carbon::today()->subDays(13);
        $daily = $base()
            ->where('created_at', '>=', $since)
            ->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $trend[] = ['date' => $day, 'count' => (int) ($daily[$day->format('Y-m-d')] ?? 0)];
        }

        // ---- Top contributors ----------------------------------------------
        $topContributors = $base()
            ->whereNotNull('causer_id')
            ->select('causer_id', DB::raw('count(*) as c'))
            ->groupBy('causer_id')
            ->orderByDesc('c')
            ->limit(5)
            ->with('causer')
            ->get();

        // ---- Activity by subject type --------------------------------------
        $byType = $base()
            ->whereNotNull('subject_type')
            ->select('subject_type', DB::raw('count(*) as c'))
            ->groupBy('subject_type')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => class_basename($row->subject_type),
                'count' => (int) $row->c,
            ]);

        return view('activity.index', [
            'activities' => $activities,
            'users' => User::orderBy('name')->get(),
            'filters' => ['causer_id' => $causerId, 'event' => $event],
            'stats' => [
                'total' => $total,
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted,
                'today' => $todayCount,
                'week' => $weekCount,
                'contributors' => $contributors,
            ],
            'trend' => $trend,
            'topContributors' => $topContributors,
            'byType' => $byType,
        ]);
    }
}
