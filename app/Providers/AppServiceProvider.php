<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

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

        // Passport scopes per API client (§14).
        Passport::tokensCan([
            'je:post' => 'Post journal entries',
            'invoice:post' => 'Post invoices',
            'reports:read' => 'Read reports',
        ]);
    }
}
