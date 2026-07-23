<?php

namespace App\Providers;

use App\Models\QuickLink;
use App\Models\Release;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The quick-links drawer renders on every page; a composer supplies its
        // data so individual controllers never have to.
        View::composer('partials.quick-links-drawer', function ($view) {
            $user = Auth::user();

            $links = QuickLink::with(['author', 'release'])
                ->visibleTo($user)
                ->orderByDesc('id')
                ->get();

            [$mine, $shared] = $links->partition(fn (QuickLink $l) => $l->user_id === $user->id);

            $view->with([
                'myQuickLinks' => $mine->values(),
                'sharedQuickLinks' => $shared->values(),
                'drawerReleases' => Release::ongoing()->orderBy('year', 'desc')->orderBy('name')->get(),
            ]);
        });
    }
}
