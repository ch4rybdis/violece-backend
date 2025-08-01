<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Matching\CompatibilityScoringService;
use App\Services\Psychology\PsychologicalScoringService;
use App\Services\Media\MediaService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register our services
        $this->app->singleton(CompatibilityScoringService::class, function ($app) {
            return new CompatibilityScoringService();
        });

        $this->app->singleton(PsychologicalScoringService::class, function ($app) {
            return new PsychologicalScoringService();
        });

        $this->app->singleton(MediaService::class, function ($app) {
            return new MediaService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Setup for PostGIS if needed
        // Add PostGIS macros to the Blueprint class
    }
}
