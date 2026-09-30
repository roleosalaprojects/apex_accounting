<?php

declare(strict_types=1);

namespace App\Events\Ledger;

use App\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;

/** A posted entry has been reversed; its source document may need to know. */
final class JournalEntryReversed
{
    use Dispatchable;

    public function __construct(
        public readonly JournalEntry $entry,
        public readonly JournalEntry $reversal,
    ) {}
}
