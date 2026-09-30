<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Workflow\ApprovalNotifier;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Sends a draft bill back: it is removed and the maker is told why. */
final class RejectDraftBill
{
    public function __construct(
        private readonly ApprovalNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Bill $draft, string $reason, User $reviewer): void
    {
        if ($draft->status !== InvoiceStatus::Draft) {
            throw new RuntimeException('Only a draft bill can be rejected.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Give the maker a reason.');
        }

        DB::transaction(function () use ($draft, $reason, $reviewer): void {
            $draft->loadMissing('vendor');
            $this->audit->record($draft->company_id, 'bill.rejected', $draft, ['total' => $draft->total->minor, 'vendor_id' => $draft->vendor_id], null, $reason);
            $this->notifier->rejected($draft, 'bill', $reason, $draft->created_by, $reviewer);
            $draft->lines()->delete();
            $draft->delete();
        });
    }
}
