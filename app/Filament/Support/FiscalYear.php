<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;

/** The current tenant's fiscal year, for "this year" figures on pages. */
final class FiscalYear
{
    /** First day of the fiscal year containing $today. */
    public static function start(?CarbonImmutable $today = null): CarbonImmutable
    {
        $today ??= CarbonImmutable::today();
        $company = Filament::getTenant();
        $startMonth = $company instanceof Company ? $company->fiscal_year_start_month : 1;

        $start = CarbonImmutable::create($today->year, $startMonth, 1);

        return $start->greaterThan($today) ? $start->subYear() : $start;
    }
}
