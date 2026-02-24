<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->loadViewsFrom(resource_path('views/vendor/laraclaw'), 'laraclaw');

        RateLimiter::for('laraclaw-api', function (Request $request): Limit {
            $perMinute = (int) config('laraclaw.rate_limits.api_per_minute', 60);

            return Limit::perMinute($perMinute)
                ->by((string) optional($request->user())->id ?: $request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too Many Requests',
                    ], 429, [
                        'Retry-After' => 60,
                    ]);
                });
        });

        RateLimiter::for('laraclaw-webhooks', function (Request $request): Limit {
            $perMinute = (int) config('laraclaw.rate_limits.webhooks_per_minute', 120);

            return Limit::perMinute($perMinute)->by($request->ip());
        });
    }
}
