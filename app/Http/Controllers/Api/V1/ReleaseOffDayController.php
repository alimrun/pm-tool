<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ReleaseOffDayRequest;
use App\Http\Resources\V1\ReleaseOffDayResource;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use Illuminate\Http\JsonResponse;

class ReleaseOffDayController extends ApiController
{
    public function index(Release $release): JsonResponse
    {
        return $this->ok(ReleaseOffDayResource::collection($release->offDays()->get()));
    }

    public function store(ReleaseOffDayRequest $request, Release $release): JsonResponse
    {
        $offDay = $release->offDays()->create($request->validated());

        return $this->created(new ReleaseOffDayResource($offDay), 'Off-day marked.');
    }

    /** Mark every Saturday and Sunday in the window that is not already off. */
    public function markWeekends(Release $release): JsonResponse
    {
        $existing = $release->offDays()->get()
            ->map(fn (ReleaseOffDay $o) => $o->date->toDateString())
            ->all();

        $added = 0;
        $cursor = $release->start_date->copy();

        while ($cursor->lte($release->end_date)) {
            if ($cursor->isWeekend() && ! in_array($cursor->toDateString(), $existing, true)) {
                $release->offDays()->create([
                    'date' => $cursor->toDateString(),
                    'reason' => 'Weekend',
                ]);
                $added++;
            }

            $cursor->addDay();
        }

        return $this->ok(
            ReleaseOffDayResource::collection($release->offDays()->get()),
            $added ? "Marked {$added} weekend day(s) as off." : 'All weekends were already marked.'
        );
    }

    public function destroy(Release $release, ReleaseOffDay $offDay): JsonResponse
    {
        abort_unless($offDay->release_id === $release->id, 404);

        $offDay->delete();

        return $this->message('Off-day removed.');
    }
}
