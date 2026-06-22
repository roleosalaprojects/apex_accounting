<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\LaravelData\DataCollection;

/**
 * Maps a POS daily sales / Z-reading into a balanced journal entry posted through
 * the chokepoint, so Apex POS never needs the chart of accounts (§14):
 *   Dr tenders (cash/card/…) + Dr discounts
 *      Cr sales (VATable/exempt/zero-rated) + Cr output VAT
 * The figures must net out; PostJournalEntry rejects an unbalanced entry.
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
        private readonly PostJournalEntry $post,
        private readonly IntegrationAccountMap $accounts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(Company $company, array $data, ?User $actor): JournalEntry
    {
        $acc = $this->accounts->pos($company->id);
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

        $reference = isset($data['reference']) ? (string) $data['reference'] : null;

        return $this->post->handle(new JournalEntryData(
            company_id: $company->id,
            entry_date: (string) $data['business_date'],
            memo: 'POS sales'.($reference !== null ? ' — '.$reference : ''),
            lines: new DataCollection(JournalLineData::class, $lines),
            source_type: 'pos.zreading',
            external_reference_no: $reference,
            created_by: $actor?->id,
            approved_by: $actor?->id,
        ), $actor);
    }
}
