<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $causerId = $request->filled('causer_id') ? (int) $request->integer('causer_id') : null;
        $event = $request->input('event');

        $activities = Activity::query()
            ->with('causer')
            ->when($causerId, fn ($q) => $q->where('causer_id', $causerId))
            ->when(in_array($event, ['created', 'updated', 'deleted'], true), fn ($q) => $q->where('event', $event))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('activity.index', [
            'activities' => $activities,
            'users' => User::orderBy('name')->get(),
            'filters' => ['causer_id' => $causerId, 'event' => $event],
        ]);
    }
}
