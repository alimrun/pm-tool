<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

/**
 * Minutes of a meeting — either linked to a release ("release-wise") or
 * general (no release). Visible to every authenticated user.
 */
class MeetingNote extends Model
{
    use RecordsActivity;

    protected $fillable = ['release_id', 'event_id', 'created_by', 'title', 'meeting_date', 'body'];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
        ];
    }

    /** Rich-text body is stored as sanitized HTML (from the Trix editor). */
    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => HtmlSanitizer::clean($value),
        );
    }

    public function bodyHtml(): HtmlString
    {
        return new HtmlString($this->body ?? '');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** The calendar meeting this note was written from, if any. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scopeForRelease(Builder $query, int $releaseId): Builder
    {
        return $query->where('release_id', $releaseId);
    }

    /** Notes not linked to any release. */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereNull('release_id');
    }

    public function activityTitle(): string
    {
        return $this->title;
    }

    public function activityReleaseId(): ?int
    {
        return $this->release_id;
    }

    protected function activityExtraIgnored(): array
    {
        // The sanitized HTML body is too noisy for the activity log.
        return ['created_by', 'body'];
    }
}
