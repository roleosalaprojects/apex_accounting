<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Enums\PeriodStatus;
use App\Exceptions\Ledger\ClosedPeriodException;
use App\Models\AccountingPeriod;

final class ReopenPeriod
{
    public function handle(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status === PeriodStatus::Locked) {
            throw ClosedPeriodException::make('a locked (year-end) period never reopens');
        }

        $period->forceFill(['status' => PeriodStatus::Open])->save();

        return $period;
    }
}
