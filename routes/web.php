<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\ReleaseDocumentController;
use App\Http\Controllers\TeamController;
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

        // Teams
        Route::get('teams/create', [TeamController::class, 'create'])->name('teams.create');
        Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
        Route::get('teams/{team}/edit', [TeamController::class, 'edit'])->name('teams.edit');
        Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
        Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
        Route::post('teams/{team}/archive', [TeamController::class, 'archive'])->name('teams.archive');
        Route::post('teams/{team}/restore', [TeamController::class, 'restore'])->name('teams.restore');

        // Releases
        Route::get('releases/create', [ReleaseController::class, 'create'])->name('releases.create');
        Route::post('releases', [ReleaseController::class, 'store'])->name('releases.store');
        Route::get('releases/{release}/edit', [ReleaseController::class, 'edit'])->name('releases.edit');
        Route::put('releases/{release}', [ReleaseController::class, 'update'])->name('releases.update');
        Route::delete('releases/{release}', [ReleaseController::class, 'destroy'])->name('releases.destroy');

        // Release documents (write)
        Route::post('releases/{release}/documents', [ReleaseDocumentController::class, 'store'])
            ->name('releases.documents.store');
        Route::delete('releases/{release}/documents/{document}', [ReleaseDocumentController::class, 'destroy'])
            ->name('releases.documents.destroy')
            ->scopeBindings();
    });

    /*
     | Read-only views available to every authenticated user (admin + viewer).
     */
    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');

    Route::get('releases/{release}', [ReleaseController::class, 'show'])->name('releases.show');
    Route::get('releases/{release}/documents/{document}', [ReleaseDocumentController::class, 'download'])
        ->name('releases.documents.download')
        ->scopeBindings();

    // Profile (Breeze)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
