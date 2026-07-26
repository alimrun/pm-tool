<?php

namespace App\Providers;

use App\Models\QuickLink;
use App\Models\Release;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        // Managing the competency catalog is org-level configuration — admin,
        // CTO, and tech lead only (team leads evaluate, they do not reconfigure).
        Gate::define('manage-competencies', fn (User $user) => $user->canManageCompetencies());

        $this->configureRateLimiting();

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

    /**
     * Named limiters for the API. Traffic is metered per access token so one
     * noisy desktop client cannot spend another's budget, falling back to the
     * IP for unauthenticated calls. Logins are metered far more tightly, keyed
     * on the submitted email *and* the caller's address, so neither guessing
     * one account from many addresses nor many accounts from one address is
     * cheap.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->currentAccessToken()?->id
                ? 'token:'.$request->user()->currentAccessToken()->id
                : 'ip:'.$request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
