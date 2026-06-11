<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';   // re-openable by admin
    case Locked = 'locked';   // year-end closed, never reopens
}
