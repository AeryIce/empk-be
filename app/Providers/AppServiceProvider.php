<?php

namespace App\Providers;

use App\Models\Foundation;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\User;
use App\Policies\PanelContentPolicy;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        if (app()->environment('production') || parse_url(config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        Gate::policy(Foundation::class, PanelContentPolicy::class);
        Gate::policy(School::class, PanelContentPolicy::class);
        Gate::policy(NewsPost::class, PanelContentPolicy::class);
        Gate::define('view-panel-diagnostics', fn (User $user): bool => $user->isPanelModerator());

        Event::listen(function (Logout $event): void {
            if ($event->guard === 'web' && $event->user instanceof User) {
                Log::info('panel.logout', ['user_id' => $event->user->getKey()]);
            }
        });
    }
}
