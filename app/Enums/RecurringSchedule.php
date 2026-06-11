<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Carbon;

enum RecurringSchedule: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';

    public function advance(Carbon $from): Carbon
    {
        return match ($this) {
            self::Monthly => $from->copy()->addMonthNoOverflow(),
            self::Quarterly => $from->copy()->addMonthsNoOverflow(3),
            self::Annually => $from->copy()->addYearNoOverflow(),
        };
    }
}
