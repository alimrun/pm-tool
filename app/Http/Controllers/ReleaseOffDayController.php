<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReleaseOffDayRequest;
use App\Models\Release;
use App\Models\ReleaseOffDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class ReleaseOffDayController extends Controller
{
    public function store(ReleaseOffDayRequest $request, Release $release): RedirectResponse
    {
        $release->offDays()->create($request->validated());

        return back()->with('success', 'Off-day marked.');
    }

    /**
     * Mark every Saturday and Sunday in the window that is not already off.
     */
    public function markWeekends(Release $release): RedirectResponse
    {
        $existing = $release->offDays()->pluck('date')->map(
            fn ($d) => Carbon::parse($d)->toDateString()
        )->all();

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

        return back()->with('success', $added
            ? "Marked {$added} weekend day(s) as off."
            : 'All weekends were already marked.');
    }

    public function destroy(Release $release, ReleaseOffDay $offDay): RedirectResponse
    {
        abort_unless($offDay->release_id === $release->id, 404);

        $offDay->delete();

        return back()->with('success', 'Off-day removed.');
    }
}
