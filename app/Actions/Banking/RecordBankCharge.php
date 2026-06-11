<?php

declare(strict_types=1);

namespace App\Actions\Banking;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Banking\BankChargeData;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\LaravelData\DataCollection;

/**
 * Records a bank charge: Dr expense / Cr bank (§8).
 */
final class RecordBankCharge
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(BankChargeData $data, ?User $actor = null): JournalEntry
    {
        return $this->post->handle(new JournalEntryData(
            company_id: $data->company_id,
            entry_date: $data->date,
            memo: $data->memo ?? 'Bank charge',
            lines: new DataCollection(JournalLineData::class, [
                new JournalLineData(account_id: $data->expense_account_id, debit: $data->amount, memo: $data->memo),
                new JournalLineData(account_id: $data->bank_account_id, credit: $data->amount, memo: $data->memo),
            ]),
            created_by: $data->created_by,
            approved_by: $data->created_by,
        ), $actor);
    }
}
