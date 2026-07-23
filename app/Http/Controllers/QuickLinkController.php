<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickLinkRequest;
use App\Models\QuickLink;
use Illuminate\Http\RedirectResponse;

class QuickLinkController extends Controller
{
    public function store(QuickLinkRequest $request): RedirectResponse
    {
        QuickLink::create($request->safe()->merge([
            'user_id' => $request->user()->id,
            'visibility' => $request->validated('visibility') ?? QuickLink::VISIBILITY_PRIVATE,
        ])->only(['user_id', 'release_id', 'label', 'url', 'visibility']));

        return back()->with('success', 'Link added.')->with('quick-links-open', true);
    }

    public function update(QuickLinkRequest $request, QuickLink $quickLink): RedirectResponse
    {
        $this->authorize('update', $quickLink);

        $quickLink->update($request->safe()->merge([
            'visibility' => $request->validated('visibility') ?? $quickLink->visibility,
        ])->only(['release_id', 'label', 'url', 'visibility']));

        return back()->with('success', 'Link updated.')->with('quick-links-open', true);
    }

    public function destroy(QuickLink $quickLink): RedirectResponse
    {
        $this->authorize('delete', $quickLink);

        $quickLink->delete();

        return back()->with('success', 'Link deleted.')->with('quick-links-open', true);
    }
}
