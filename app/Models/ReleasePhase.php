<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReleasePhase extends Model
{
    protected $fillable = ['release_id', 'phase', 'position', 'start_date', 'end_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'position' => 'integer',
        ];
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    public function label(): string
    {
        return Release::PHASES[$this->phase] ?? ucfirst($this->phase);
    }
}
