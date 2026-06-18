<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Models\Invoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use RuntimeException;

/**
 * Converts a sales order / quotation into a posted Invoice, reusing PostInvoice
 * so the ledger impact still flows through the one posting chokepoint. The
 * order's lines mirror InvoiceLineData, so conversion is a direct map.
 */
final class SalesOrderService
{
    public function __construct(private readonly PostInvoice $postInvoice) {}

    public function convertToInvoice(SalesOrder $order, ?User $actor = null): Invoice
    {
        if ($order->invoice_id !== null) {
            throw new RuntimeException('This sales order has already been invoiced.');
        }
        if ($order->status === 'cancelled') {
            throw new RuntimeException('A cancelled sales order cannot be invoiced.');
        }

        $order->loadMissing('lines');
        if ($order->lines->isEmpty()) {
            throw new RuntimeException('Add at least one line before invoicing.');
        }

        $lines = $order->lines->map(fn (SalesOrderLine $l): array => [
            'item_id' => $l->item_id,
            'description' => $l->description,
            'qty' => (string) $l->qty,
            'unit_price' => (int) $l->unit_price,
            'tax_code_id' => $l->tax_code_id,
            'income_account_id' => $l->income_account_id,
        ])->all();

        $invoice = $this->postInvoice->handle(InvoiceData::from([
            'company_id' => $order->company_id,
            'customer_id' => $order->customer_id,
            'invoice_date' => $order->order_date->toDateString(),
            'pricing_mode' => $order->pricing_mode,
            'reference_no' => $order->reference,
            'lines' => $lines,
        ]), $actor);

        $order->update(['invoice_id' => $invoice->id, 'status' => 'invoiced']);

        return $invoice;
    }
}
