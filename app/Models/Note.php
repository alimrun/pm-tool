<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_SHARED = 'shared';

    /** @var array<string, string> */
    public const VISIBILITIES = [
        self::VISIBILITY_PRIVATE => 'Private',
        self::VISIBILITY_SHARED => 'Shared',
    ];

    protected $fillable = ['user_id', 'date', 'body', 'visibility'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isShared(): bool
    {
        return $this->visibility === self::VISIBILITY_SHARED;
    }

    public function visibilityLabel(): string
    {
        return self::VISIBILITIES[$this->visibility] ?? ucfirst($this->visibility);
    }

    /**
     * Notes the given user may see: everyone's shared notes plus their own.
     * Private notes belonging to other users are never included.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('visibility', self::VISIBILITY_SHARED)
                ->orWhere('user_id', $user->id);
        });
    }
}
