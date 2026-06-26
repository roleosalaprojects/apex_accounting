<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a POS Z-reading in the integration inbox (§14): it arrives via the
 * API as Pending, and an admin either imports it (creating a draft journal entry)
 * or dismisses it. Nothing reaches the ledger without a deliberate import.
 */
enum PosZReadingStatus: string
{
    case Pending = 'pending';
    case Imported = 'imported';
    case Dismissed = 'dismissed';
}
