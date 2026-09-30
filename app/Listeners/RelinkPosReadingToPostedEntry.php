<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Ledger\DraftJournalEntryPosted;
use App\Models\PosZReading;

/**
 * An imported Z-reading points at the draft its import created. Posting the
 * draft replaces it, so point the reading at the posted entry instead.
 */
final class RelinkPosReadingToPostedEntry
{
    public function handle(DraftJournalEntryPosted $event): void
    {
        if ($event->draft->source_type !== 'pos.zreading') {
            return;
        }

        PosZReading::query()->withoutGlobalScopes()
            ->where('company_id', $event->draft->company_id)
            ->where('journal_entry_id', $event->draft->id)
            ->update(['journal_entry_id' => $event->posted->id]);
    }
}
