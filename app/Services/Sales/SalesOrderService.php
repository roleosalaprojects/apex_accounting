<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Numbering\NumberGenerator;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fulfils a sales order in parts: goods go out on numbered delivery receipts,
 * and each invoice covers some of what was ordered, through PostInvoice so
 * the ledger impact still flows through the one posting chokepoint. Lines
 * remember how much has been delivered and invoiced, so nothing ships or
 * bills twice.
 */
final class SalesOrderService
{
    public function __construct(
        private readonly PostInvoice $postInvoice,
        private readonly NumberGenerator $numbers,
    ) {}

    /**
     * Ship part of the order: quantities keyed by sales order line id.
     *
     * @param  array<int, string|int|float>  $quantities
     */
    public function deliver(SalesOrder $order, array $quantities, string $date, ?string $receivedBy = null, ?string $notes = null, ?User $actor = null): Delivery
    {
        return DB::transaction(function () use ($order, $quantities, $date, $receivedBy, $notes, $actor): Delivery {
            if ($order->status === 'cancelled') {
                throw new RuntimeException('A cancelled sales order cannot be delivered.');
            }
            $lines = $this->pick($order, $quantities, fn (SalesOrderLine $line): int => $line->unitsToDeliver(), 'to deliver');

            $delivery = new Delivery;
            $delivery->forceFill([
                'company_id' => $order->company_id,
                'sales_order_id' => $order->id,
                'delivery_date' => $date,
                'received_by' => $receivedBy,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);
            $delivery->number = $this->numbers->next($order->company_id, 'delivery', Carbon::parse($date)->year);
            $delivery->save();

            foreach ($lines as [$line, $units]) {
                $delivery->lines()->create([
                    'sales_order_line_id' => $line->id,
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    'qty' => Quantity::fromUnits($units),
                ]);
                $line->forceFill(['delivered_qty' => Quantity::fromUnits(Quantity::toUnits($line->delivered_qty) + $units)])->save();
            }

            return $delivery->load('lines');
        });
    }

    /**
     * Invoice part of the order: quantities keyed by sales order line id.
     *
     * @param  array<int, string|int|float>  $quantities
     */
    public function invoice(SalesOrder $order, array $quantities, ?User $actor = null, ?string $date = null): Invoice
    {
        return DB::transaction(function () use ($order, $quantities, $actor, $date): Invoice {
            if ($order->status === 'cancelled') {
                throw new RuntimeException('A cancelled sales order cannot be invoiced.');
            }
            $lines = $this->pick($order, $quantities, fn (SalesOrderLine $line): int => $line->unitsToInvoice(), 'to invoice');

            $invoice = $this->postInvoice->handle(InvoiceData::from([
                'company_id' => $order->company_id,
                'customer_id' => $order->customer_id,
                'invoice_date' => $date ?? now()->toDateString(),
                'pricing_mode' => $order->pricing_mode,
                'reference_no' => $order->reference,
                'sales_order_id' => $order->id,
                'lines' => array_map(fn (array $picked): array => [
                    'item_id' => $picked[0]->item_id,
                    'description' => $picked[0]->description,
                    'qty' => Quantity::fromUnits($picked[1]),
                    'unit_price' => (int) $picked[0]->unit_price,
                    'tax_code_id' => $picked[0]->tax_code_id,
                    'income_account_id' => $picked[0]->income_account_id,
                ], $lines),
            ]), $actor);

            foreach ($lines as [$line, $units]) {
                $line->forceFill(['invoiced_qty' => Quantity::fromUnits(Quantity::toUnits($line->invoiced_qty) + $units)])->save();
            }

            $order->refresh();
            $fullyInvoiced = $order->lines->every(fn (SalesOrderLine $line): bool => $line->unitsToInvoice() <= 0);
            $order->update(['invoice_id' => $invoice->id, 'status' => $fullyInvoiced ? 'invoiced' : 'partially_invoiced']);

            return $invoice;
        });
    }

    /** Invoice everything still open on the order. */
    public function convertToInvoice(SalesOrder $order, ?User $actor = null): Invoice
    {
        $order->loadMissing('lines');
        if ($order->lines->isEmpty()) {
            throw new RuntimeException('Add at least one line before invoicing.');
        }

        $remaining = [];
        foreach ($order->lines as $line) {
            if ($line->unitsToInvoice() > 0) {
                $remaining[$line->id] = Quantity::fromUnits($line->unitsToInvoice());
            }
        }
        if ($remaining === []) {
            throw new RuntimeException('This sales order has already been invoiced.');
        }

        return $this->invoice($order, $remaining, $actor, $order->order_date->toDateString());
    }

    /**
     * What to invoice next: whatever was delivered but not yet invoiced, or —
     * when nothing has shipped on a delivery receipt — everything still open.
     *
     * @return array<int, string>
     */
    public function suggestedInvoiceQuantities(SalesOrder $order): array
    {
        $order->loadMissing('lines');
        $delivered = [];
        $open = [];
        foreach ($order->lines as $line) {
            if ($line->unitsDeliveredUninvoiced() > 0) {
                $delivered[$line->id] = Quantity::compact($line->unitsDeliveredUninvoiced());
            }
            if ($line->unitsToInvoice() > 0) {
                $open[$line->id] = Quantity::compact($line->unitsToInvoice());
            }
        }

        return $delivered !== [] ? $delivered : $open;
    }

    /**
     * Resolve the requested quantities against the order's lines, refusing
     * more than each line still has.
     *
     * @param  array<int, string|int|float>  $quantities
     * @param  callable(SalesOrderLine): int  $available
     * @return list<array{0: SalesOrderLine, 1: int}>
     */
    private function pick(SalesOrder $order, array $quantities, callable $available, string $what): array
    {
        $order->loadMissing('lines');
        $picked = [];
        foreach ($quantities as $lineId => $qty) {
            $units = Quantity::toUnits($qty);
            if ($units <= 0) {
                continue;
            }
            /** @var SalesOrderLine|null $line */
            $line = $order->lines->firstWhere('id', (int) $lineId);
            if ($line === null) {
                throw new RuntimeException("Line {$lineId} is not on this order.");
            }
            if ($units > $available($line)) {
                throw new RuntimeException(sprintf('%s: only %s left %s.', $line->description, Quantity::compact(max(0, $available($line))), $what));
            }
            $picked[] = [$line, $units];
        }
        if ($picked === []) {
            throw new RuntimeException('Enter a quantity on at least one line.');
        }

        return $picked;
    }
}
