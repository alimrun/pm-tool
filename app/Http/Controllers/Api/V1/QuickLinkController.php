<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\QuickLinkRequest;
use App\Http\Resources\V1\QuickLinkResource;
use App\Models\QuickLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved bookmarks. Like personal notes, a quick link belongs to its author —
 * QuickLinkPolicy lets nobody else edit or delete one, leads included.
 */
class QuickLinkController extends ApiController
{
    /**
     * The caller's visible links, partitioned into their own and everyone
     * else's shared ones — the split the drawer renders, done once on the
     * server so two clients cannot disagree about which bucket a link is in.
     */
    public function index(Request $request): JsonResponse
    {
        $links = QuickLink::with(['author', 'release'])
            ->visibleTo($request->user())
            ->when($this->filterId($request, 'release_id'), fn ($q, $id) => $q->where('release_id', $id))
            ->orderByDesc('id')
            ->get();

        [$mine, $shared] = $links->partition(fn (QuickLink $l) => $l->user_id === $request->user()->id);

        return $this->ok([
            'mine' => QuickLinkResource::collection($mine->values())->resolve($request),
            'shared' => QuickLinkResource::collection($shared->values())->resolve($request),
        ]);
    }

    public function store(QuickLinkRequest $request): JsonResponse
    {
        $link = QuickLink::create($request->safe()->merge([
            'user_id' => $request->user()->id,
            'visibility' => $request->validated('visibility') ?? QuickLink::VISIBILITY_PRIVATE,
        ])->only(['user_id', 'release_id', 'label', 'url', 'visibility']));

        return $this->created(new QuickLinkResource($link->load(['author', 'release'])), 'Link added.');
    }

    public function update(QuickLinkRequest $request, QuickLink $quickLink): JsonResponse
    {
        $this->authorize('update', $quickLink);

        $quickLink->update($request->safe()->merge([
            'visibility' => $request->validated('visibility') ?? $quickLink->visibility,
        ])->only(['release_id', 'label', 'url', 'visibility']));

        return $this->ok(new QuickLinkResource($quickLink->load(['author', 'release'])), 'Link updated.');
    }

    public function destroy(QuickLink $quickLink): JsonResponse
    {
        $this->authorize('delete', $quickLink);

        $quickLink->delete();

        return $this->message('Link deleted.');
    }
}
