<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'deactivated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsActivity;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CTO = 'cto';
    public const ROLE_TEAM_LEAD = 'team_lead';
    public const ROLE_DEVELOPER = 'developer';
    public const ROLE_QA = 'qa';
    public const ROLE_VIEWER = 'viewer';

    /**
     * All supported roles, slug => label.
     *
     * @var array<string, string>
     */
    public const ROLES = [
        self::ROLE_ADMIN => 'Admin',
        self::ROLE_CTO => 'CTO',
        self::ROLE_TEAM_LEAD => 'Team Lead',
        self::ROLE_DEVELOPER => 'Developer',
        self::ROLE_QA => 'QA',
        self::ROLE_VIEWER => 'Viewer',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTeamLead(): bool
    {
        return $this->role === self::ROLE_TEAM_LEAD;
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    /** Admins and CTOs may manage user accounts. */
    public function canManageUsers(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_CTO], true);
    }

    /** Admins and team leads may plan releases (create/edit, off-days, documents). */
    public function canManageReleases(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_TEAM_LEAD], true);
    }

    /** Admins and team leads may add/remove people on a team. */
    public function canManageTeamMembers(): bool
    {
        return $this->canManageReleases();
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deactivated_at');
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /** Teams this user belongs to. */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    /** Releases this user is assigned to. */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function attendedEvents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    public function activityTitle(): string
    {
        return $this->name;
    }

    public function activityReleaseId(): ?int
    {
        return null;
    }

    protected function activityExtraIgnored(): array
    {
        return ['email_verified_at'];
    }
}
