<?php

namespace App\Providers;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Registers the Shield policies that fall outside Laravel's discovery,
        // including the one for Shield's own Role model.
        FilamentShield::enforcePolicies();

        // Strict mode also covers the test environment, otherwise lazy-loading
        // violations only surface in the browser and never in the suite.
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);

        Carbon::setLocale(config('app.locale'));
    }
}
