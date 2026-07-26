<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\ActivityResource;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The app-wide audit feed, restricted to full-access roles.
 *
 * Performance scores are deliberately absent from this feed: PerformanceScore
 * does not use the RecordsActivity trait, precisely because the feed is
 * readable by every full-access user and ratings are not.
 */
class ActivityController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = Activity::query()
            ->with('causer')
            ->when($this->filterId($request, 'causer_id'), fn ($q, $id) => $q->where('causer_id', $id))
            ->when($this->filterId($request, 'release_id'), fn ($q, $id) => $q->where('release_id', $id))
            ->when(
                in_array($request->input('event'), ['created', 'updated', 'deleted'], true),
                fn ($q) => $q->where('event', $request->input('event'))
            )
            ->when($request->filled('subject_type'), fn ($q) => $q->where(
                'subject_type',
                'App\\Models\\'.Str::studly($request->string('subject_type')->toString())
            ))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', Carbon::parse($request->input('from'))->toDateString()))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', Carbon::parse($request->input('to'))->toDateString()))
            ->latest();

        return $this->paginate($request, $query, ActivityResource::class);
    }

    /**
     * Headline counts, a fortnight of daily volume, the top contributors, and
     * the busiest subject types — optionally focused on one user.
     */
    public function stats(Request $request): JsonResponse
    {
        $causerId = $this->filterId($request, 'causer_id');

        $base = fn () => Activity::query()->when($causerId, fn ($q) => $q->where('causer_id', $causerId));

        $eventCounts = $base()
            ->select('event', DB::raw('count(*) as aggregate'))
            ->groupBy('event')
            ->pluck('aggregate', 'event');

        $created = (int) ($eventCounts['created'] ?? 0);
        $updated = (int) ($eventCounts['updated'] ?? 0);
        $deleted = (int) ($eventCounts['deleted'] ?? 0);

        // Daily volume for the trailing fortnight, with quiet days filled as zero
        // rather than missing — a client charting this needs a continuous axis.
        $since = Carbon::today()->subDays(13);
        $daily = $base()
            ->where('created_at', '>=', $since)
            ->select(DB::raw('date(created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->pluck('c', 'd');

        $trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $trend[] = [
                'date' => $day->toDateString(),
                'count' => (int) ($daily[$day->format('Y-m-d')] ?? 0),
            ];
        }

        $topContributors = $base()
            ->whereNotNull('causer_id')
            ->select('causer_id', DB::raw('count(*) as c'))
            ->groupBy('causer_id')
            ->orderByDesc('c')
            ->limit(5)
            ->with('causer')
            ->get()
            ->map(fn ($row) => [
                'user' => $row->causer ? (new UserSummaryResource($row->causer))->resolve($request) : null,
                'count' => (int) $row->c,
            ]);

        $byType = $base()
            ->whereNotNull('subject_type')
            ->select('subject_type', DB::raw('count(*) as c'))
            ->groupBy('subject_type')
            ->orderByDesc('c')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'type' => Str::snake(class_basename($row->subject_type)),
                'label' => Str::headline(class_basename($row->subject_type)),
                'count' => (int) $row->c,
            ]);

        return $this->ok([
            'totals' => [
                'total' => $created + $updated + $deleted,
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted,
                'today' => (int) $base()->whereDate('created_at', Carbon::today())->count(),
                'week' => (int) $base()->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())->count(),
                'contributors' => (int) $base()->whereNotNull('causer_id')->distinct()->count('causer_id'),
            ],
            'trend' => $trend,
            'top_contributors' => $topContributors,
            'by_subject_type' => $byType,
        ]);
    }
}
