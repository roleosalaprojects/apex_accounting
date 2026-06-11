<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voids a posted invoice by reversing its journal entry (reason required) and
 * marking it voided. Blocked when payments or credit memos are applied — those
 * must be unapplied/voided first (§6.2).
 */
final class VoidInvoice
{
    public function __construct(private readonly ReverseJournalEntry $reverse) {}

    public function handle(Invoice $invoice, string $reason, ?User $actor = null): Invoice
    {
        if (! $invoice->status->isPosted()) {
            throw new RuntimeException('Only a posted invoice can be voided.');
        }

        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $invoice->loadCount(['paymentApplications', 'creditMemoApplications']);

            if ($invoice->payment_applications_count > 0 || $invoice->credit_memo_applications_count > 0) {
                throw new RuntimeException('Unapply payments/credit memos before voiding this invoice.');
            }

            if ($invoice->journal_entry_id !== null) {
                $this->reverse->handle($invoice->journalEntry, $reason, actor: $actor);
            }

            $invoice->forceFill(['status' => InvoiceStatus::Voided])->save();

            return $invoice;
        });
    }
}
