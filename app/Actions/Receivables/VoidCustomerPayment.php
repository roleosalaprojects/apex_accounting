<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\InvoiceStatus;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\PaymentApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voids a customer payment: reverses its entry (reason required), so the
 * cash and any creditable withholding leave again, and unapplies it from its
 * invoices, which are open again.
 */
final class VoidCustomerPayment
{
    public function __construct(private readonly ReverseJournalEntry $reverse) {}

    public function handle(CustomerPayment $payment, string $reason, ?User $actor = null): CustomerPayment
    {
        if ($payment->status !== 'posted') {
            throw new RuntimeException('Only a posted payment can be voided.');
        }

        return DB::transaction(function () use ($payment, $reason, $actor): CustomerPayment {
            if ($payment->journal_entry_id !== null) {
                $this->reverse->handle($payment->journalEntry, $reason, actor: $actor);
            }

            $invoices = $payment->applications()->with('invoice')->get()
                ->map(fn (PaymentApplication $application): ?Invoice => $application->invoice)
                ->filter();
            $payment->applications()->delete();
            $invoices->each(fn (Invoice $invoice) => $invoice->forceFill(['status' => self::statusFor($invoice)])->save());

            $payment->forceFill(['status' => 'voided'])->save();

            return $payment;
        });
    }

    /** What is still owed on the invoice once this payment no longer counts. */
    private static function statusFor(Invoice $invoice): InvoiceStatus
    {
        $outstanding = $invoice->outstanding();

        return match (true) {
            $outstanding <= 0 => InvoiceStatus::Paid,
            $outstanding < $invoice->total->minor => InvoiceStatus::PartiallyPaid,
            default => InvoiceStatus::Posted,
        };
    }
}
