<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PosZReadingStatus;
use App\Events\Ledger\JournalEntryReversed;
use App\Models\PosZReading;

/**
 * Reversing the entry of an imported Z-reading undoes the import: the
 * reading goes back to the integration inbox, to be corrected and imported
 * again (or dismissed).
 */
final class ReleasePosReadingOnReversal
{
    public function handle(JournalEntryReversed $event): void
    {
        if ($event->entry->source_type !== 'pos.zreading') {
            return;
        }

        PosZReading::query()->withoutGlobalScopes()
            ->where('company_id', $event->entry->company_id)
            ->whereKey($event->entry->source_id)
            ->where('status', PosZReadingStatus::Imported->value)
            ->update([
                'status' => PosZReadingStatus::Pending->value,
                'journal_entry_id' => null,
                'imported_by' => null,
                'imported_at' => null,
            ]);
    }
}
