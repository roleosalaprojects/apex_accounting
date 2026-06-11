<?php

declare(strict_types=1);

namespace App\Actions\Banking;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Banking\DepositData;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\LaravelData\DataCollection;

/**
 * Records a deposit: Dr bank / Cr source (§8).
 */
final class RecordDeposit
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(DepositData $data, ?User $actor = null): JournalEntry
    {
        return $this->post->handle(new JournalEntryData(
            company_id: $data->company_id,
            entry_date: $data->date,
            memo: $data->memo ?? 'Bank deposit',
            lines: new DataCollection(JournalLineData::class, [
                new JournalLineData(account_id: $data->bank_account_id, debit: $data->amount, memo: $data->memo),
                new JournalLineData(account_id: $data->source_account_id, credit: $data->amount, memo: $data->memo),
            ]),
            created_by: $data->created_by,
            approved_by: $data->created_by,
        ), $actor);
    }
}
