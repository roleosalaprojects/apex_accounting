<?php

declare(strict_types=1);

namespace App\Actions\Ledger;

use App\Enums\PeriodStatus;
use App\Exceptions\Ledger\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Services\Ledger\LedgerBalanceCalculator;
use Illuminate\Support\Facades\DB;

final class ClosePeriod
{
    public function __construct(private readonly LedgerBalanceCalculator $balances) {}

    public function handle(AccountingPeriod $period): AccountingPeriod
    {
        if ($period->status === PeriodStatus::Locked) {
            throw ClosedPeriodException::make('a locked period cannot be re-closed');
        }

        return DB::transaction(function () use ($period): AccountingPeriod {
            // Recompute the whole chain so openings tie out forward (§4.1).
            $this->balances->persistAll($period->company_id);

            $period->forceFill(['status' => PeriodStatus::Closed])->save();

            return $period;
        });
    }
}
