<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Looser rate limit for mobile (employees may have shared IPs)
        RateLimiter::for('mobile', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        $this->routes(function () {
            // Web app + admin API — uses Sanctum auth, /api prefix
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Mobile API — uses /Webservice prefix (hardcoded in mobile APK)
            // No auth middleware — endpoints are public, mobile sends userID per request
            // This MUST stay at /Webservice/* because mobile v5.4 has BASE_URL hardcoded
            Route::middleware('api')
                ->prefix('Webservice')
                ->group(base_path('routes/webservice.php'));

            // Mobile root-level endpoint (called via different Retrofit instance)
            // GET /underMaintenance — startup check
            Route::middleware('api')
                ->group(base_path('routes/mobile_root.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}