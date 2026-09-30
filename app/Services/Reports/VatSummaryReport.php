<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\VatAllocation;

/**
 * VAT Summary / 2550Q working paper (§12.14): sales and output VAT from the
 * sales book, creditable input VAT from the purchase book's directly
 * attributable input VAT plus the quarter's saved common-VAT allocation, and
 * VAT payable or excess input carryover.
 *
 * Built from the period's documents, never from movement on the VAT accounts:
 * those also carry the previous quarter's remittance, which would otherwise
 * read as negative sales.
 */
final class VatSummaryReport
{
    public function __construct(
        private readonly SalesBook $salesBook,
        private readonly PurchaseBook $purchaseBook,
    ) {}

    /**
     * @return array{exempt_sales: int, zero_rated_sales: int, vatable_sales: int, output_vat: int, creditable_input_vat: int, vat_payable: int, carryover: int, allocation_id: int|null}
     */
    public function build(int $companyId, int $fiscalYear, int $quarter, string $from, string $asOf): array
    {
        $sales = $this->salesBook->build($companyId, $from, $asOf)['totals'];
        $purchases = $this->purchaseBook->build($companyId, $from, $asOf)['totals'];

        $allocation = VatAllocation::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('fiscal_year', $fiscalYear)->where('quarter', $quarter)->first();

        $outputVat = $sales['output_vat'];
        $creditableInput = $purchases['input_vat_direct'] + ($allocation?->creditable->minor ?? 0);
        $vatPayable = $outputVat - $creditableInput;

        return [
            'exempt_sales' => $sales['exempt'],
            'zero_rated_sales' => $sales['zero_rated'],
            'vatable_sales' => $sales['vatable'],
            'output_vat' => $outputVat,
            'creditable_input_vat' => $creditableInput,
            'vat_payable' => max(0, $vatPayable),
            'carryover' => max(0, -$vatPayable),
            'allocation_id' => $allocation?->id,
        ];
    }
}
