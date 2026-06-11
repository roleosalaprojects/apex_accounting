<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\JournalStatus;
use App\Models\VatAllocation;
use Illuminate\Support\Facades\DB;

/**
 * VAT Summary / 2550Q working paper (§12.14). Output VAT, input VAT (direct +
 * allocated common, citing the saved allocation record), exempt sales on their
 * own line, and VAT payable or excess input carryover.
 */
final class VatSummaryReport
{
    public function __construct(private readonly SalesBook $salesBook) {}

    /**
     * @return array{exempt_sales: int, zero_rated_sales: int, vatable_sales: int, output_vat: int, creditable_input_vat: int, vat_payable: int, carryover: int, allocation_id: int|null}
     */
    public function build(int $companyId, int $fiscalYear, int $quarter, string $from, string $asOf): array
    {
        $sales = $this->salesBook->build($companyId, $from, $asOf)['totals'];

        $outputVat = -$this->netSigned($companyId, '2200', $from, $asOf); // credit-normal -> credit positive
        $creditableInput = $this->netSigned($companyId, '1400', $from, $asOf); // debit-normal

        $allocation = VatAllocation::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('fiscal_year', $fiscalYear)->where('quarter', $quarter)->first();

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

    private function netSigned(int $companyId, string $code, string $from, string $asOf): int
    {
        $row = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.code', $code)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')
            ->first();

        return (int) ($row->net ?? 0);
    }
}
