<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Rate limits for every public endpoint. Keys use the visitor's IP
        // (and the account for signed-in actions).
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by('login:'.$r->ip()),
            Limit::perHour(30)->by('login-h:'.$r->ip()),
        ]);
        RateLimiter::for('chat', fn (Request $r) => Limit::perMinute(40)->by('chat:'.$r->ip()));
        RateLimiter::for('feed', fn (Request $r) => Limit::perMinute(60)->by('feed:'.$r->ip()));
        RateLimiter::for('channel', fn (Request $r) => Limit::perMinute(12)->by('channel:'.($r->user()?->id ?? $r->ip())));
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)->by('api:'.$r->ip()));
    }
}
