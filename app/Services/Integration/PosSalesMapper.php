<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Data\Ledger\JournalLineData;

/**
 * Maps a POS daily sales / Z-reading payload into balanced journal lines, so Apex
 * POS never needs the chart of accounts (§14):
 *   Dr tenders (cash/card/…) + Dr discounts
 *      Cr sales (VATable/exempt/zero-rated) + Cr output VAT
 * This is a pure mapper — it builds lines and reports totals but never posts.
 * Importing a staged Z-reading (ImportPosZReading) feeds these lines into a DRAFT
 * journal entry for review; the figures must net out or the draft is rejected.
 */
final class PosSalesMapper
{
    /** @var list<string> */
    private const TENDERS = ['cash', 'card', 'ewallet', 'cheque', 'bank_transfer', 'gift_cert'];

    /** @var array<string, string> sales line accounts keyed by account map key => payload field */
    private const SALES = [
        'sales_vatable' => 'vatable_sales',
        'sales_exempt' => 'exempt_sales',
        'sales_zero_rated' => 'zero_rated_sales',
        'output_vat' => 'vat_amount',
    ];

    public function __construct(
        private readonly IntegrationAccountMap $accounts,
    ) {}

    /**
     * Build the balanced journal lines for a Z-reading payload.
     *
     * @param  array<string, mixed>  $data
     * @return list<JournalLineData>
     */
    public function lines(int $companyId, array $data): array
    {
        $acc = $this->accounts->pos($companyId);
        $lines = [];

        $tenders = is_array($data['tenders'] ?? null) ? $data['tenders'] : [];
        foreach (self::TENDERS as $tender) {
            $amount = (int) ($tenders[$tender] ?? 0);
            if ($amount !== 0) {
                $lines[] = new JournalLineData(account_id: $acc[$tender], debit: $amount, memo: ucfirst(str_replace('_', ' ', $tender)).' collections');
            }
        }

        $discount = (int) ($data['discounts'] ?? 0);
        if ($discount !== 0) {
            $lines[] = new JournalLineData(account_id: $acc['discount'], debit: $discount, memo: 'Sales discounts');
        }

        foreach (self::SALES as $accKey => $field) {
            $amount = (int) ($data[$field] ?? 0);
            if ($amount !== 0) {
                $lines[] = new JournalLineData(account_id: $acc[$accKey], credit: $amount, memo: ucfirst(str_replace('_', ' ', $field)));
            }
        }

        return $lines;
    }

    /**
     * Arithmetic debit/credit totals for a payload, without resolving accounts —
     * used to reject an inconsistent Z-reading at the API boundary.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: int, 1: int} [debits, credits] in minor units
     */
    public function totals(array $data): array
    {
        $tenders = is_array($data['tenders'] ?? null) ? $data['tenders'] : [];

        $debits = (int) ($data['discounts'] ?? 0);
        foreach (self::TENDERS as $tender) {
            $debits += (int) ($tenders[$tender] ?? 0);
        }

        $credits = 0;
        foreach (self::SALES as $field) {
            $credits += (int) ($data[$field] ?? 0);
        }

        return [$debits, $credits];
    }
}
