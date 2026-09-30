<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Workflow\ApprovalNotifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Sends a draft invoice back: it is removed and the maker is told why. */
final class RejectDraftInvoice
{
    public function __construct(
        private readonly ApprovalNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Invoice $draft, string $reason, User $reviewer): void
    {
        if ($draft->status !== InvoiceStatus::Draft) {
            throw new RuntimeException('Only a draft invoice can be rejected.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Give the maker a reason.');
        }

        DB::transaction(function () use ($draft, $reason, $reviewer): void {
            $draft->loadMissing('customer');
            $this->audit->record($draft->company_id, 'invoice.rejected', $draft, ['total' => $draft->total->minor, 'customer_id' => $draft->customer_id], null, $reason);
            $this->notifier->rejected($draft, 'invoice', $reason, $draft->created_by, $reviewer);
            $draft->lines()->delete();
            $draft->delete();
        });
    }
}
