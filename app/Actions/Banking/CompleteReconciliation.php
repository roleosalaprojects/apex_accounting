<?php

declare(strict_types=1);

namespace App\Actions\Banking;

use App\Models\Reconciliation;
use App\Services\Banking\BankBalanceService;
use RuntimeException;

/**
 * Completes a reconciliation only when the cleared difference is zero (§8):
 * the cleared book balance must equal the statement ending balance.
 */
final class CompleteReconciliation
{
    public function __construct(private readonly BankBalanceService $balances) {}

    public function handle(Reconciliation $reconciliation): Reconciliation
    {
        if ($reconciliation->status === 'completed') {
            throw new RuntimeException('Reconciliation already completed.');
        }

        $clearedBalance = $this->balances->clearedBalance($reconciliation->id);
        $difference = $reconciliation->statement_ending_balance->minor - $clearedBalance;

        if ($difference !== 0) {
            throw new RuntimeException("Reconciliation does not balance (difference {$difference}).");
        }

        $reconciliation->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
        ])->save();

        return $reconciliation;
    }
}
