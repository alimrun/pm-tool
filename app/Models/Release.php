<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Release extends Model
{
    protected $fillable = [
        'project_id', 'team_id', 'name', 'year', 'quarter', 'start_date', 'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'year' => 'integer',
            'quarter' => 'integer',
        ];
    }

    /**
     * Canonical, ordered phases every release has.
     *
     * @var array<string, string>
     */
    public const PHASES = [
        'development' => 'Development',
        'qa' => 'QA',
        'retest' => 'Retest',
        'release' => 'Release',
    ];

    /**
     * Fixed colors for phase segments on the timeline.
     *
     * @var array<string, string>
     */
    public const PHASE_COLORS = [
        'development' => '#6366f1', // indigo
        'qa' => '#f59e0b',          // amber
        'retest' => '#f97316',      // orange
        'release' => '#10b981',     // emerald
    ];

    protected static function booted(): void
    {
        // DB-level cascade removes phase/document rows, but stored files must be
        // cleaned up explicitly since the cascade bypasses Eloquent events.
        static::deleting(function (Release $release) {
            foreach ($release->documents as $document) {
                Storage::disk('local')->delete($document->path);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ReleasePhase::class)->orderBy('position');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ReleaseDocument::class)->latest();
    }

    public function quarterLabel(): string
    {
        return 'Q'.$this->quarter;
    }

    public function durationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
