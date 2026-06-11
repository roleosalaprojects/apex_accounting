<?php

declare(strict_types=1);

namespace App\Actions\Banking;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Banking\TransferData;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Models\JournalEntry;
use App\Models\User;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Records a transfer between cash/bank accounts: Dr to / Cr from (§8).
 */
final class RecordTransfer
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(TransferData $data, ?User $actor = null): JournalEntry
    {
        if ($data->from_account_id === $data->to_account_id) {
            throw new RuntimeException('Cannot transfer to the same account.');
        }

        return $this->post->handle(new JournalEntryData(
            company_id: $data->company_id,
            entry_date: $data->date,
            memo: $data->memo ?? 'Fund transfer',
            lines: new DataCollection(JournalLineData::class, [
                new JournalLineData(account_id: $data->to_account_id, debit: $data->amount, memo: $data->memo),
                new JournalLineData(account_id: $data->from_account_id, credit: $data->amount, memo: $data->memo),
            ]),
            created_by: $data->created_by,
            approved_by: $data->created_by,
        ), $actor);
    }
}
