<?php

declare(strict_types=1);

namespace App\Services\Receivables;

use App\Data\Receivables\InvoiceData;
use App\Enums\PricingMode;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxCode;
use App\Services\Tax\TaxValidator;
use App\Services\Tax\VatMath;
use App\Support\Quantity;
use RuntimeException;

/**
 * The arithmetic of an invoice (§6.2, §16.4): each line's net, VAT and
 * effective tags, and the VATable / exempt / zero-rated totals — shared by
 * posting and by drafts awaiting approval, so both show the same figures.
 */
final class InvoiceLineCalculator
{
    public function __construct(
        private readonly VatMath $vat,
        private readonly TaxValidator $taxValidator,
    ) {}

    /**
     * @return array{lines: array<int, array{model: array<string, mixed>, net: int, vat: int, income_account_id: int, tax_code_id: int, dims: array<string, int|null>}>, totals: array{vatable: int, vat: int, exempt: int, zero: int, total: int}}
     */
    public function compute(Company $company, InvoiceData $data): array
    {
        $taxCodes = TaxCode::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->get()->keyBy('id');
        $lines = [];
        $totals = ['vatable' => 0, 'vat' => 0, 'exempt' => 0, 'zero' => 0, 'total' => 0];
        $lineNo = 1;

        foreach ($data->lines as $lineData) {
            /** @var TaxCode|null $taxCode */
            $taxCode = $taxCodes->get($lineData->tax_code_id);
            if ($taxCode === null) {
                throw new RuntimeException("Tax code {$lineData->tax_code_id} not found.");
            }

            $lineIsVatExempt = $lineData->item_id !== null
                && Item::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)->whereKey($lineData->item_id)
                    ->value('is_vat_exempt_item');

            $this->taxValidator->assertAllowed($taxCode, $company->taxpayer_type, (bool) $lineIsVatExempt);

            $units = Quantity::toUnits($lineData->qty);
            $gross = Quantity::extend($lineData->unit_price, $units);

            $breakdown = $data->pricing_mode === PricingMode::VatInclusive
                ? $this->vat->fromInclusive($gross, $taxCode->rate_bp)
                : $this->vat->fromExclusive($gross, $taxCode->rate_bp);

            $net = $breakdown->base;
            $vat = $breakdown->vat;
            $lineTotal = $net + $vat;

            if ($taxCode->isExempt()) {
                $totals['exempt'] += $net;
            } elseif ($taxCode->isZeroRated()) {
                $totals['zero'] += $net;
            } else {
                $totals['vatable'] += $net;
            }
            $totals['vat'] += $vat;
            $totals['total'] += $lineTotal;

            $dims = [
                'department_id' => $lineData->department_id ?? $data->department_id,
                'project_id' => $lineData->project_id ?? $data->project_id,
                'fund_id' => $lineData->fund_id ?? $data->fund_id,
                'branch_id' => $lineData->branch_id ?? $data->branch_id,
            ];

            $lines[] = [
                'model' => array_merge([
                    'line_no' => $lineNo++,
                    'item_id' => $lineData->item_id,
                    'description' => $lineData->description,
                    'qty' => $lineData->qty,
                    'unit_price' => $lineData->unit_price,
                    'tax_code_id' => $taxCode->id,
                    'line_total' => $net,
                    'vat_amount' => $vat,
                    'income_account_id' => $lineData->income_account_id,
                    'sales_order_line_id' => $lineData->sales_order_line_id,
                ], $dims),
                'net' => $net,
                'vat' => $vat,
                'income_account_id' => $lineData->income_account_id,
                'tax_code_id' => $taxCode->id,
                'dims' => $dims,
            ];
        }

        return ['lines' => $lines, 'totals' => $totals];
    }
}
