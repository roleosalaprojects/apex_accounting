<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';
}
