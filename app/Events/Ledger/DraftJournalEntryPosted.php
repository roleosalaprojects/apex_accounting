<?php

declare(strict_types=1);

namespace App\Events\Ledger;

use App\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A draft has been posted as a new entry and is about to be deleted, so
 * anything pointing at the draft can move to the posted entry.
 */
final class DraftJournalEntryPosted
{
    use Dispatchable;

    public function __construct(
        public readonly JournalEntry $draft,
        public readonly JournalEntry $posted,
    ) {}
}
