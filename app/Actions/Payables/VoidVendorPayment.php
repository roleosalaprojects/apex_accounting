<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\BillApplication;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\WithholdingTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voids a vendor payment: reverses its entry (reason required), so the cash
 * and the EWT withheld come back; unapplies it from its bills, which are owed
 * again; and drops its withholding record so it leaves the 2307 / 0619-E /
 * 1601-EQ figures.
 */
final class VoidVendorPayment
{
    public function __construct(private readonly ReverseJournalEntry $reverse) {}

    public function handle(VendorPayment $payment, string $reason, ?User $actor = null): VendorPayment
    {
        if ($payment->status !== 'posted') {
            throw new RuntimeException('Only a posted payment can be voided.');
        }

        return DB::transaction(function () use ($payment, $reason, $actor): VendorPayment {
            if ($payment->journal_entry_id !== null) {
                $this->reverse->handle($payment->journalEntry, $reason, actor: $actor);
            }

            $bills = $payment->applications()->with('bill')->get()
                ->map(fn (BillApplication $application): ?Bill => $application->bill)
                ->filter();
            $payment->applications()->delete();
            $bills->each(fn (Bill $bill) => $bill->forceFill(['status' => self::statusFor($bill)])->save());

            WithholdingTransaction::query()->withoutGlobalScopes()->where('vendor_payment_id', $payment->id)->delete();

            $payment->forceFill(['status' => 'voided'])->save();

            return $payment;
        });
    }

    /** What is owed on the bill once this payment no longer counts. */
    private static function statusFor(Bill $bill): InvoiceStatus
    {
        $outstanding = $bill->outstanding();

        return match (true) {
            $outstanding <= 0 => InvoiceStatus::Paid,
            $outstanding < $bill->total->minor => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Posted,
        };
    }
}
