<?php

declare(strict_types=1);

namespace App\Services\Receivables;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/**
 * Recomputes an invoice's payment status from its applications (§6.2). Never
 * touches a voided invoice.
 */
final class InvoiceStatusRecalculator
{
    public function recalculate(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Voided) {
            return $invoice;
        }

        $applied = $invoice->appliedAmount();
        $total = $invoice->total->minor;

        $status = match (true) {
            $applied <= 0 => InvoiceStatus::Posted,
            $applied >= $total => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };

        $invoice->forceFill(['status' => $status])->save();

        return $invoice;
    }
}
