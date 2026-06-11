<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Ledger\OpeningBalancesData;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Guided cutover (§4.1b), GL component: one balanced opening JE dated the day
 * before the first open period, each account's opening balance offset to
 * `3950 Opening Balance Equity`. The residual that accumulates in 3950 must
 * equal the equity figure from the old books and is never auto-cleared.
 *
 * Open AR/AP documents and inventory quantities are loaded by their own
 * phase actions (Phase 3/4/6) referencing the same Opening Balance Equity.
 */
final class SetupOpeningBalances
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(OpeningBalancesData $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $obe = Account::query()
                ->withoutGlobalScopes()
                ->where('company_id', $data->company_id)
                ->where('code', '3950')
                ->first();

            if ($obe === null) {
                throw new RuntimeException('No Opening Balance Equity (3950) account configured.');
            }

            $lines = [];
            $totalDebits = 0;
            $totalCredits = 0;

            foreach ($data->lines as $line) {
                $lines[] = $line;
                $totalDebits += $line->debit;
                $totalCredits += $line->credit;
            }

            // Plug the imbalance into Opening Balance Equity.
            $diff = $totalDebits - $totalCredits;
            if ($diff > 0) {
                $lines[] = new JournalLineData(account_id: $obe->id, credit: $diff, memo: 'Opening balance equity');
            } elseif ($diff < 0) {
                $lines[] = new JournalLineData(account_id: $obe->id, debit: -$diff, memo: 'Opening balance equity');
            }

            $entryData = new JournalEntryData(
                company_id: $data->company_id,
                entry_date: $data->opening_date,
                memo: 'Opening balances (cutover)',
                lines: new DataCollection(JournalLineData::class, $lines),
                created_by: $data->created_by,
                approved_by: $data->created_by,
            );

            return $this->post->handle($entryData);
        });
    }
}
