<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridges the Filament tenant (active company switcher) into our CompanyContext
 * so the global CompanyScope and Actions resolve the right company (§2, §13).
 */
final class SetCompanyContextFromTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Company) {
            app(CompanyContext::class)->set($tenant->id);
        }

        return $next($request);
    }
}
