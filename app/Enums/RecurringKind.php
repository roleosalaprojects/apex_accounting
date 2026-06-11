<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringKind: string
{
    case JournalEntry = 'journal_entry';
    case Invoice = 'invoice';
    case Bill = 'bill';
    case DepreciationRun = 'depreciation_run';
}
