<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuickLinkController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\ReleaseDocumentController;
use App\Http\Controllers\MeetingNoteController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ReleaseOffDayController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TasksheetController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    /*
     | Admin-only writes. Registered first so `.../create` and `.../edit`
     | resolve before the `{model}` show routes below.
     */
    Route::middleware('admin')->group(function () {
        // Projects
        Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');

        // Teams (settings/lifecycle stay admin-only; membership is opened below)
        Route::get('teams/create', [TeamController::class, 'create'])->name('teams.create');
        Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
        Route::get('teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
        Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('teams/{team}/archive', [TeamController::class, 'archive'])->name('teams.archive');
        Route::post('teams/{team}/restore', [TeamController::class, 'restore'])->name('teams.restore');
    });

    /*
     | Release planning & team membership — admins and team leads.
     | Registered before the `{model}` show routes so `.../create` and
     | `.../edit` resolve first.
     */
    Route::middleware('manage-releases')->group(function () {
        // Team membership
        Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
        Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember'])
            ->name('teams.members.destroy');

        // Releases
        Route::get('releases/create', [ReleaseController::class, 'create'])->name('releases.create');
        Route::post('releases', [ReleaseController::class, 'store'])->name('releases.store');
        Route::get('releases/{release}/edit', [ReleaseController::class, 'edit'])->name('releases.edit');
        Route::put('releases/{release}', [ReleaseController::class, 'update'])->name('releases.update');
        Route::delete('releases/{release}', [ReleaseController::class, 'destroy'])->name('releases.destroy');
        Route::post('releases/{release}/complete', [ReleaseController::class, 'complete'])->name('releases.complete');
        Route::post('releases/{release}/reopen', [ReleaseController::class, 'reopen'])->name('releases.reopen');

        // Release documents (write)
        Route::post('releases/{release}/documents', [ReleaseDocumentController::class, 'store'])
            ->name('releases.documents.store');
        Route::delete('releases/{release}/documents/{document}', [ReleaseDocumentController::class, 'destroy'])
            ->name('releases.documents.destroy')
            ->scopeBindings();

        // Off-days are part of the plan.
        Route::post('releases/{release}/off-days', [ReleaseOffDayController::class, 'store'])
            ->name('releases.offdays.store');
        Route::post('releases/{release}/off-days/weekends', [ReleaseOffDayController::class, 'markWeekends'])
            ->name('releases.offdays.weekends');
        Route::delete('releases/{release}/off-days/{offDay}', [ReleaseOffDayController::class, 'destroy'])
            ->name('releases.offdays.destroy')
            ->scopeBindings();
    });

    /*
     | Planning views — hidden from limited roles (developer, QA). New planning
     | routes belong inside this `full-access` group.
     */
    Route::middleware('full-access')->group(function () {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

        Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
        Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');

        Route::get('releases', [ReleaseController::class, 'index'])->name('releases.index');
        Route::get('releases/{release}', [ReleaseController::class, 'show'])->name('releases.show');
        Route::get('releases/{release}/documents/{document}', [ReleaseDocumentController::class, 'download'])
            ->name('releases.documents.download')
            ->scopeBindings();

        // Release-scoped collaboration writes live with the release pages.
        Route::post('releases/{release}/tasks', [TaskController::class, 'store'])->name('releases.tasks.store');
        Route::post('releases/{release}/comments', [CommentController::class, 'storeForRelease'])->name('releases.comments.store');

        // Activity feed
        Route::get('activity', [ActivityController::class, 'index'])->name('activity.index');
    });

    /*
     | Collaboration — any authenticated user (admin or viewer) may participate.
     */
    // Tasks & subtasks
    Route::post('tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Comments on tasks (release comments live in the full-access group above)
    Route::post('tasks/{task}/comments', [CommentController::class, 'storeForTask'])->name('tasks.comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Kanban board
    Route::get('board', [BoardController::class, 'index'])->name('board.index');
    Route::post('board/tasks', [BoardController::class, 'storeTask'])->name('board.tasks.store');
    Route::patch('board/tasks/{task}', [BoardController::class, 'move'])->name('board.move');

    // Calendar & events (any authenticated user creates; creator/admin edits)
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('events', [EventController::class, 'store'])->name('events.store');
    Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

    // Daily notes (private / shared)
    Route::get('notes', [NoteController::class, 'index'])->name('notes.index');
    Route::post('notes', [NoteController::class, 'store'])->name('notes.store');
    Route::put('notes/{note}', [NoteController::class, 'update'])->name('notes.update');
    Route::delete('notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

    // Meeting notes (release-wise or general; author edits, author/admin deletes)
    Route::get('meeting-notes', [MeetingNoteController::class, 'index'])->name('meeting-notes.index');
    Route::get('meeting-notes/create', [MeetingNoteController::class, 'create'])->name('meeting-notes.create');
    Route::post('meeting-notes', [MeetingNoteController::class, 'store'])->name('meeting-notes.store');
    Route::get('meeting-notes/{meetingNote}', [MeetingNoteController::class, 'show'])->name('meeting-notes.show');
    Route::get('meeting-notes/{meetingNote}/edit', [MeetingNoteController::class, 'edit'])->name('meeting-notes.edit');
    Route::put('meeting-notes/{meetingNote}', [MeetingNoteController::class, 'update'])->name('meeting-notes.update');
    Route::delete('meeting-notes/{meetingNote}', [MeetingNoteController::class, 'destroy'])->name('meeting-notes.destroy');

    // Quick links (drawer; limited roles are private-only)
    Route::post('quick-links', [QuickLinkController::class, 'store'])->name('quick-links.store');
    Route::put('quick-links/{quickLink}', [QuickLinkController::class, 'update'])->name('quick-links.update');
    Route::delete('quick-links/{quickLink}', [QuickLinkController::class, 'destroy'])->name('quick-links.destroy');

    // Team tasksheet (daily grid; feedback column is lead-only)
    Route::get('tasksheet', [TasksheetController::class, 'index'])->name('tasksheet.index');
    Route::put('tasksheet/entries', [TasksheetController::class, 'upsert'])->name('tasksheet.entries.upsert');
    Route::get('tasksheet/users/{member}', [TasksheetController::class, 'user'])
        ->name('tasksheet.user')
        ->withTrashed(); // history pages must resolve deleted users too

    /*
     | User management — Admins and CTOs only.
     */
    Route::middleware('manage-users')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
