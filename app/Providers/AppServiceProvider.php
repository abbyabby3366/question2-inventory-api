<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Observers\FlushProductCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        // Fail loudly in development on lazy loading (N+1), silently discarded attributes, etc.
        Model::shouldBeStrict(! $this->app->isProduction());

        Product::observe(FlushProductCache::class);
        Category::observe(FlushProductCache::class);
        Supplier::observe(FlushProductCache::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // General API traffic: per user when authenticated, otherwise per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip()));

        // Credential endpoints: strict, to slow down brute-force and credential-stuffing attempts.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by('auth:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by('auth-ip:'.$request->ip()),
        ]);
    }
}
