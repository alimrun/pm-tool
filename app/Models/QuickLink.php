<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved bookmark shown in the quick-links drawer — private to its author or
 * shared. Limited roles (developer/QA) are private-only: they neither see
 * others' shared links nor create shared ones.
 */
class QuickLink extends Model
{
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE => 'Private',
        self::VISIBILITY_SHARED => 'Shared',
    ];

    protected $fillable = ['user_id', 'release_id', 'label', 'url', 'visibility'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function isShared(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED;
    }

    /**
     * Links the given user may see: their own — plus, for full-access roles
     * only, everyone's shared links. Limited roles see nothing but their own.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasLimitedAccess()) {
            return $query->where('user_id', $user->id);
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_SHARED)
                ->orWhere('user_id', $user->id);
        });
    }
}
