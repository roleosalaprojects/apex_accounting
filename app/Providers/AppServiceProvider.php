<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class);
    }

    public function boot(): void
    {
        // Money is integers; guard against silent attribute typos in dev/CI.
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);
    }
}
