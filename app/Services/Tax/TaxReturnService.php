<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\TaxReturnType;
use App\Models\Company;
use App\Models\TaxReturn;
use App\Services\Reports\EwtSummaryReport;
use App\Services\Reports\ProfitAndLossReport;
use App\Services\Reports\SalesBook;
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
        private readonly SalesBook $salesBook,
        private readonly ProfitAndLossReport $pnl,
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
            TaxReturnType::Pct2551Q => $this->percentageTax($company->id, $from, $to),
            TaxReturnType::IncomeTax1702Q => $this->incomeTax($company, $fiscalYear, $quarter),
            TaxReturnType::IncomeTax1701Q => $this->individualIncomeTax($company, $fiscalYear, $quarter),
        };
    }

    /**
     * 1702Q quarterly income tax: cumulative (YTD) book net income at the 25%
     * regular corporate rate, floored at zero. The base is book net income
     * before tax adjustments — a working figure, not the final taxable income.
     *
     * @return array{net_income: int, rate: float, tax_due: int, basis: string}
     */
    private function incomeTax(Company $company, int $fiscalYear, int $quarter): array
    {
        $from = $this->quarterRange($company, $fiscalYear, 1)['from'];
        $to = $this->quarterRange($company, $fiscalYear, $quarter)['to'];

        $netIncome = $this->pnl->build($company->id, $from, $to)['net_income'];
        $rate = 0.25;

        return [
            'net_income' => $netIncome,
            'rate' => $rate,
            'tax_due' => max(0, (int) round($netIncome * $rate)),
            'basis' => 'cumulative book net income (before tax adjustments)',
        ];
    }

    /**
     * 1701Q quarterly individual income tax: cumulative (YTD) book net income at
     * the TRAIN graduated rates (effective 2023). A working figure on book net
     * income before tax adjustments.
     *
     * @return array{net_income: int, tax_due: int, basis: string}
     */
    private function individualIncomeTax(Company $company, int $fiscalYear, int $quarter): array
    {
        $from = $this->quarterRange($company, $fiscalYear, 1)['from'];
        $to = $this->quarterRange($company, $fiscalYear, $quarter)['to'];

        $netIncome = $this->pnl->build($company->id, $from, $to)['net_income'];

        return [
            'net_income' => $netIncome,
            'tax_due' => $this->graduatedTax(max(0, $netIncome)),
            'basis' => 'cumulative book net income, graduated rates (TRAIN, 2023 onwards)',
        ];
    }

    /** Graduated individual income tax (TRAIN, effective 2023) on a centavo amount. */
    private function graduatedTax(int $taxable): int
    {
        return match (true) {
            $taxable <= 250_000_00 => 0,
            $taxable <= 400_000_00 => (int) round(0.15 * ($taxable - 250_000_00)),
            $taxable <= 800_000_00 => (int) round(22_500_00 + 0.20 * ($taxable - 400_000_00)),
            $taxable <= 2_000_000_00 => (int) round(102_500_00 + 0.25 * ($taxable - 800_000_00)),
            $taxable <= 8_000_000_00 => (int) round(402_500_00 + 0.30 * ($taxable - 2_000_000_00)),
            default => (int) round(2_202_500_00 + 0.35 * ($taxable - 8_000_000_00)),
        };
    }

    /**
     * 2551Q percentage tax: 3% of gross sales/receipts (NIRC §116), for non-VAT
     * taxpayers.
     *
     * @return array{gross_receipts: int, rate: float, tax_due: int}
     */
    private function percentageTax(int $companyId, string $from, string $to): array
    {
        $gross = $this->salesBook->build($companyId, $from, $to)['totals']['total'];
        $rate = 0.03;

        return [
            'gross_receipts' => $gross,
            'rate' => $rate,
            'tax_due' => (int) round($gross * $rate),
        ];
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
