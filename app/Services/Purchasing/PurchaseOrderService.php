<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use RuntimeException;

/**
 * Converts a purchase order into a posted Bill, reusing PostBill so the ledger
 * impact still flows through the one posting chokepoint. The order's lines
 * mirror BillLineData, so conversion is a direct map.
 */
final class PurchaseOrderService
{
    public function __construct(private readonly PostBill $postBill) {}

    public function convertToBill(PurchaseOrder $order, ?User $actor = null): Bill
    {
        if ($order->bill_id !== null) {
            throw new RuntimeException('This purchase order has already been billed.');
        }
        if ($order->status === 'cancelled') {
            throw new RuntimeException('A cancelled purchase order cannot be billed.');
        }

        $order->loadMissing('lines');
        if ($order->lines->isEmpty()) {
            throw new RuntimeException('Add at least one line before billing.');
        }

        $lines = $order->lines->map(fn (PurchaseOrderLine $l): array => [
            'item_id' => $l->item_id,
            'description' => $l->description,
            'qty' => (string) $l->qty,
            'unit_price' => (int) $l->unit_price,
            'tax_code_id' => $l->tax_code_id,
            'vat_bucket' => $l->vat_bucket,
            'expense_or_asset_account_id' => $l->expense_account_id,
        ])->all();

        $bill = $this->postBill->handle(BillData::from([
            'company_id' => $order->company_id,
            'vendor_id' => $order->vendor_id,
            'bill_date' => $order->order_date->toDateString(),
            'pricing_mode' => $order->pricing_mode,
            'external_reference_no' => $order->reference,
            'lines' => $lines,
        ]), $actor);

        $order->update(['bill_id' => $bill->id, 'status' => 'billed']);

        return $bill;
    }
}
