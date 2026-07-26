<?php

namespace App\Services;

use App\Models\QuickLink;
use App\Models\Release;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Saved bookmarks for the quick-links drawer.
 *
 * Like personal notes, a quick link belongs to its author — QuickLinkPolicy lets
 * nobody else edit or delete one, leads included. Limited roles
 * (developer/QA) are private-only: they neither see others' shared links nor
 * create shared ones, which the model's `visibleTo` scope enforces on read and
 * QuickLinkRequest enforces on write.
 */
class QuickLinkService
{
    /** The attributes a quick-link write accepts. */
    private const WRITABLE = ['release_id', 'label', 'url', 'visibility'];

    /**
     * Links the viewer may see, newest first.
     *
     * @return Builder<QuickLink>
     */
    public function visibleTo(User $viewer, ?int $releaseId = null): Builder
    {
        return QuickLink::query()
            ->with(['author', 'release'])
            ->visibleTo($viewer)
            ->when($releaseId, fn ($q, $id) => $q->where('release_id', $id))
            ->orderByDesc('id');
    }

    /**
     * The drawer's split: the viewer's own links, and everyone else's shared
     * ones. Partitioned here so two consumers cannot disagree about which
     * bucket a link belongs in.
     *
     * @return array{mine: Collection<int, QuickLink>, shared: Collection<int, QuickLink>}
     */
    public function partitionedFor(User $viewer, ?int $releaseId = null): array
    {
        [$mine, $shared] = $this->visibleTo($viewer, $releaseId)
            ->get()
            ->partition(fn (QuickLink $link) => $link->user_id === $viewer->id);

        return ['mine' => $mine->values(), 'shared' => $shared->values()];
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, User $author): QuickLink
    {
        return QuickLink::create($this->writable($attributes) + [
            'user_id' => $author->id,
            'visibility' => $attributes['visibility'] ?? QuickLink::VISIBILITY_PRIVATE,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(QuickLink $link, array $attributes): QuickLink
    {
        $link->update($this->writable($attributes) + [
            'visibility' => $attributes['visibility'] ?? $link->visibility,
        ]);

        return $link;
    }

    /**
     * Ongoing releases, for the drawer's "attach to a release" picker.
     *
     * @return Collection<int, Release>
     */
    public function attachableReleases(): Collection
    {
        return Release::ongoing()->orderBy('year', 'desc')->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function writable(array $attributes): array
    {
        return array_filter(
            array_intersect_key($attributes, array_flip(self::WRITABLE)),
            fn ($value, $key) => $key !== 'visibility' || $value !== null,
            ARRAY_FILTER_USE_BOTH
        );
    }
}
