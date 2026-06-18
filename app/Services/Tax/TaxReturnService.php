<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\TaxReturnType;
use App\Models\Company;
use App\Models\TaxReturn;
use App\Services\Reports\EwtSummaryReport;
use App\Services\Reports\VatSummaryReport;
use Illuminate\Support\Carbon;

/**
 * Prepares and persists BIR return filings (§12). Figures are computed by the
 * existing report services and snapshotted into a TaxReturn so a filed return
 * is immutable evidence of what was reported for the period.
 */
final class TaxReturnService
{
    public function __construct(
        private readonly VatSummaryReport $vat,
        private readonly EwtSummaryReport $ewt,
    ) {}

    /**
     * The calendar dates of a fiscal quarter, anchored to the company's fiscal
     * year start month.
     *
     * @return array{from: string, to: string}
     */
    public function quarterRange(Company $company, int $fiscalYear, int $quarter): array
    {
        $start = Carbon::create($fiscalYear, $company->fiscal_year_start_month, 1)
            ->startOfDay()
            ->addMonthsNoOverflow(($quarter - 1) * 3);
        $end = $start->copy()->addMonthsNoOverflow(3)->subDay();

        return ['from' => $start->toDateString(), 'to' => $end->toDateString()];
    }

    /**
     * @return array<string, mixed>
     */
    public function compute(Company $company, TaxReturnType $type, int $fiscalYear, int $quarter): array
    {
        ['from' => $from, 'to' => $to] = $this->quarterRange($company, $fiscalYear, $quarter);

        return match ($type) {
            TaxReturnType::Vat2550Q => $this->vat->build($company->id, $fiscalYear, $quarter, $from, $to),
            TaxReturnType::Ewt1601EQ => $this->ewt->build($company->id, $from, $to),
        };
    }

    public function prepare(Company $company, TaxReturnType $type, int $fiscalYear, int $quarter, ?int $userId): TaxReturn
    {
        ['from' => $from, 'to' => $to] = $this->quarterRange($company, $fiscalYear, $quarter);

        return TaxReturn::query()->create([
            'company_id' => $company->id,
            'type' => $type->value,
            'fiscal_year' => $fiscalYear,
            'quarter' => $quarter,
            'period_start' => $from,
            'period_end' => $to,
            'figures' => $this->compute($company, $type, $fiscalYear, $quarter),
            'status' => 'draft',
            'created_by' => $userId,
        ]);
    }
}
