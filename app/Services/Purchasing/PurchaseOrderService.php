<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Enums\InvoiceStatus;
use App\Models\Bill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bills a purchase order as the goods arrive, in one or more bills, through
 * PostBill so the ledger impact (and stock receipt) still flows through the
 * one posting chokepoint. Lines remember how much has been billed.
 */
final class PurchaseOrderService
{
    public function __construct(private readonly PostBill $postBill) {}

    /**
     * Bill part of the order: quantities keyed by purchase order line id.
     *
     * @param  array<int, string|int|float>  $quantities
     */
    public function bill(PurchaseOrder $order, array $quantities, ?User $actor = null, ?string $date = null): Bill
    {
        return DB::transaction(function () use ($order, $quantities, $actor, $date): Bill {
            if ($order->status === 'cancelled') {
                throw new RuntimeException('A cancelled purchase order cannot be billed.');
            }
            $order->loadMissing('lines');

            $picked = [];
            foreach ($quantities as $lineId => $qty) {
                $units = Quantity::toUnits($qty);
                if ($units <= 0) {
                    continue;
                }
                /** @var PurchaseOrderLine|null $line */
                $line = $order->lines->firstWhere('id', (int) $lineId);
                if ($line === null) {
                    throw new RuntimeException("Line {$lineId} is not on this order.");
                }
                if ($units > $line->unitsToBill()) {
                    throw new RuntimeException(sprintf('%s: only %s left to bill.', $line->description, Quantity::compact(max(0, $line->unitsToBill()))));
                }
                $picked[] = [$line, $units];
            }
            if ($picked === []) {
                throw new RuntimeException('Enter a quantity on at least one line.');
            }

            $bill = $this->postBill->handle(BillData::from([
                'company_id' => $order->company_id,
                'vendor_id' => $order->vendor_id,
                'bill_date' => $date ?? now()->toDateString(),
                'pricing_mode' => $order->pricing_mode,
                'external_reference_no' => $order->reference,
                'purchase_order_id' => $order->id,
                'lines' => array_map(fn (array $p): array => [
                    'item_id' => $p[0]->item_id,
                    'description' => $p[0]->description,
                    'qty' => Quantity::fromUnits($p[1]),
                    'unit_price' => (int) $p[0]->unit_price,
                    'tax_code_id' => $p[0]->tax_code_id,
                    'vat_bucket' => $p[0]->vat_bucket,
                    'expense_or_asset_account_id' => $p[0]->expense_account_id,
                    'purchase_order_line_id' => $p[0]->id,
                ], $picked),
            ]), $actor);

            foreach ($picked as [$line, $units]) {
                $line->forceFill(['billed_qty' => Quantity::fromUnits(Quantity::toUnits($line->billed_qty) + $units)])->save();
            }

            $order->refresh();
            $fullyBilled = $order->lines->every(fn (PurchaseOrderLine $line): bool => $line->unitsToBill() <= 0);
            $order->update(['bill_id' => $bill->id, 'status' => $fullyBilled ? 'billed' : 'partially_billed']);

            return $bill;
        });
    }

    /** A voided bill hands its quantities back to the order it came from. */
    public function release(Bill $bill): void
    {
        if ($bill->purchase_order_id === null) {
            return;
        }
        /** @var PurchaseOrder|null $order */
        $order = PurchaseOrder::query()->withoutGlobalScopes()->with('lines')->find($bill->purchase_order_id);
        if ($order === null) {
            return;
        }

        foreach ($bill->lines()->whereNotNull('purchase_order_line_id')->get() as $line) {
            /** @var PurchaseOrderLine|null $orderLine */
            $orderLine = $order->lines->firstWhere('id', $line->purchase_order_line_id);
            if ($orderLine === null) {
                continue;
            }
            $orderLine->forceFill(['billed_qty' => Quantity::fromUnits(max(0, Quantity::toUnits($orderLine->billed_qty) - Quantity::toUnits($line->qty)))])->save();
        }

        $order->refresh();
        $anyBilled = $order->lines->contains(fn (PurchaseOrderLine $line): bool => Quantity::toUnits($line->billed_qty) > 0);
        $latest = $order->bills()->where('status', '!=', InvoiceStatus::Voided)->orderByDesc('id')->value('id');
        $order->update(['bill_id' => $latest, 'status' => $anyBilled ? 'partially_billed' : 'sent']);
    }

    /** Bill everything still open on the order. */
    public function convertToBill(PurchaseOrder $order, ?User $actor = null): Bill
    {
        $order->loadMissing('lines');
        if ($order->lines->isEmpty()) {
            throw new RuntimeException('Add at least one line before billing.');
        }

        $remaining = [];
        foreach ($order->lines as $line) {
            if ($line->unitsToBill() > 0) {
                $remaining[$line->id] = Quantity::fromUnits($line->unitsToBill());
            }
        }
        if ($remaining === []) {
            throw new RuntimeException('This purchase order has already been billed.');
        }

        return $this->bill($order, $remaining, $actor, $order->order_date->toDateString());
    }
}
