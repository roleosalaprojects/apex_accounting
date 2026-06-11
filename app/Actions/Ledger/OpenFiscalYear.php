<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates the 12 monthly accounting periods for a fiscal year. Posting never
 * creates periods on demand (§4.2), so a year must be opened first.
 */
final class OpenFiscalYear
{
    /**
     * @return array<int, AccountingPeriod>
     */
    public function handle(Company $company, int $fiscalYear): array
    {
        return DB::transaction(function () use ($company, $fiscalYear): array {
            $startMonth = $company->fiscal_year_start_month;
            $periods = [];

            for ($i = 0; $i < 12; $i++) {
                $periodNo = $i + 1;
                $monthOffset = $startMonth - 1 + $i;
                $year = $fiscalYear + intdiv($monthOffset, 12);
                $month = ($monthOffset % 12) + 1;

                $start = Carbon::create($year, $month, 1);

                $periods[] = AccountingPeriod::query()->firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'fiscal_year' => $fiscalYear,
                        'period_no' => $periodNo,
                    ],
                    [
                        'starts_on' => $start->toDateString(),
                        'ends_on' => $start->copy()->endOfMonth()->toDateString(),
                        'status' => PeriodStatus::Open,
                    ],
                );
            }

            return $periods;
        });
    }
}
