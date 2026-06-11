<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Enums\InvoiceStatus;
use App\Models\Bill;

/**
 * Recomputes a bill's payment status from its applications (§7). Mirrors the
 * AR recalculator; reuses the shared posted/partially_paid/paid states.
 */
final class BillStatusRecalculator
{
    public function recalculate(Bill $bill): Bill
    {
        if ($bill->status === InvoiceStatus::Voided) {
            return $bill;
        }

        $applied = $bill->appliedAmount();
        $total = $bill->total->minor;

        $status = match (true) {
            $applied <= 0 => InvoiceStatus::Posted,
            $applied >= $total => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };

        $bill->forceFill(['status' => $status])->save();

        return $bill;
    }
}
