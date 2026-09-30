<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Models\Bill;
use App\Models\DebitMemo;
use App\Services\Payables\BillStatusRecalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a posted debit memo to one or more of the vendor's bills (no GL
 * effect — the GL moved at PostDebitMemo; this settles the bills at subledger
 * level and rolls their status).
 */
final class ApplyDebitMemo
{
    public function __construct(private readonly BillStatusRecalculator $recalculator) {}

    /**
     * @param  array<int, array{bill_id: int, amount: int}>  $applications
     */
    public function handle(DebitMemo $memo, array $applications): DebitMemo
    {
        return DB::transaction(function () use ($memo, $applications): DebitMemo {
            if (! in_array($memo->status, ['posted', 'applied'], true)) {
                throw new RuntimeException('Only a posted debit memo can be applied.');
            }

            $available = $memo->total->minor - (int) $memo->applications()->sum('amount');

            foreach ($applications as $application) {
                $amount = $application['amount'];
                if ($amount <= 0) {
                    throw new RuntimeException('Application amount must be positive.');
                }
                if ($amount > $available) {
                    throw new RuntimeException('Debit memo application exceeds the credit still available.');
                }

                /** @var Bill $bill */
                $bill = Bill::query()->withoutGlobalScopes()
                    ->where('company_id', $memo->company_id)
                    ->findOrFail($application['bill_id']);

                if ($bill->vendor_id !== $memo->vendor_id) {
                    throw new RuntimeException("Bill {$bill->number} belongs to another vendor.");
                }
                if ($amount > $bill->outstanding()) {
                    throw new RuntimeException("Application exceeds bill {$bill->number} outstanding.");
                }

                $memo->applications()->create(['bill_id' => $bill->id, 'amount' => $amount]);
                $available -= $amount;

                $this->recalculator->recalculate($bill->fresh());
            }

            $memo->forceFill(['status' => 'applied'])->save();

            return $memo;
        });
    }
}
