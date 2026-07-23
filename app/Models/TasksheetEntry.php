<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

/**
 * One member's row on a team's daily tasksheet: morning plan, day-end result,
 * points and tickets — or an absence (casual/sick leave). The `feedback`
 * column is the team lead's private note and must only ever be rendered
 * behind a User::isLead() gate.
 */
class TasksheetEntry extends Model
{
    use RecordsActivity;
    /** @var array<string, string> */
    public const LEAVE_TYPES = [
        'casual' => 'Casual leave',
        'sick' => 'Sick leave',
    ];

    /**
     * The fillable task columns that make up a "complete" day's row.
     *
     * @var array<int, string>
     */
    public const TASK_FIELDS = [
        'plan', 'result', 'comment', 'tickets', 'work_points', 'ticket_count', 'ticket_points',
    ];

    protected $fillable = [
        'team_id', 'user_id', 'date', 'plan', 'result', 'comment', 'tickets',
        'work_points', 'ticket_count', 'ticket_points', 'leave_type', 'feedback',
    ];

    protected function casts(): array
    {
        // team_id/user_id are cast because they can arrive as form-input
        // strings on an unsaved row — the policy compares user_id strictly.
        return [
            'date' => 'date',
            'team_id' => 'integer',
            'user_id' => 'integer',
            'work_points' => 'integer',
            'ticket_count' => 'integer',
            'ticket_points' => 'integer',
        ];
    }

    /**
     * Rich-text columns (Trix) store sanitized HTML; visually-empty markup is
     * stored as null so it never counts as a filled field.
     */
    private function richText(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => HtmlSanitizer::isEmpty($value) ? null : HtmlSanitizer::clean($value),
        );
    }

    protected function plan(): Attribute
    {
        return $this->richText();
    }

    protected function result(): Attribute
    {
        return $this->richText();
    }

    protected function comment(): Attribute
    {
        return $this->richText();
    }

    protected function tickets(): Attribute
    {
        return $this->richText();
    }

    protected function feedback(): Attribute
    {
        return $this->richText();
    }

    /** Sanitized HTML of a rich-text field, ready to render unescaped. */
    public function html(string $field): HtmlString
    {
        return new HtmlString($this->{$field} ?? '');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isOnLeave(): bool
    {
        return $this->leave_type !== null;
    }

    public function filledFieldCount(): int
    {
        return collect(self::TASK_FIELDS)->filter(fn (string $f) => filled($this->{$f}))->count();
    }

    /** Every task field has a value (0 counts as a value). */
    public function isFullyFilled(): bool
    {
        return ! $this->isOnLeave() && $this->filledFieldCount() === count(self::TASK_FIELDS);
    }

    /** Some task fields have values, but not all — the row still needs work. */
    public function isPartiallyFilled(): bool
    {
        $count = $this->filledFieldCount();

        return ! $this->isOnLeave() && $count > 0 && $count < count(self::TASK_FIELDS);
    }

    public function leaveLabel(): string
    {
        return self::LEAVE_TYPES[$this->leave_type] ?? ucfirst((string) $this->leave_type);
    }

    /**
     * Whether this row was first saved after its sheet day had passed.
     * Creation time is what counts — later edits never set (or clear) this.
     */
    public function wasFilledLate(): bool
    {
        return $this->created_at !== null
            && $this->created_at->gt($this->date->copy()->endOfDay());
    }

    public function activityTitle(): string
    {
        return ($this->member?->name ?? 'Member #'.$this->user_id).' · '.$this->date->format('M j, Y');
    }

    protected function activityDescription(string $event): string
    {
        return sprintf('%s tasksheet row “%s”', ucfirst($event), $this->activityTitle());
    }

    /**
     * The activity feed is visible to every authenticated user, while
     * `feedback` is lead-only — it must never be logged, not even as a diff.
     */
    protected function activityExtraIgnored(): array
    {
        return ['feedback', 'team_id', 'user_id'];
    }
}
