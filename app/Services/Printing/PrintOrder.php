<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\TaxCode;
use App\Support\AmountInWords;
use App\Support\Money;
use App\Support\Quantity;
use Barryvdh\DomPDF\Facade\Pdf;

/** Purchase Order and Sales Order prints, which share a layout. */
final class PrintOrder
{
    public function purchaseOrder(PurchaseOrder $order): string
    {
        $order->loadMissing(['vendor', 'company', 'createdBy', 'lines']);

        return Pdf::loadView('print.order', $this->data($order, [
            'title' => 'PURCHASE ORDER',
            'partyLabel' => 'Supplier',
            'party' => $order->vendor,
            'secondDateLabel' => 'Expected',
            'secondDate' => $order->expected_date?->format('M j, Y'),
            'acknowledgeLabel' => 'Accepted by (supplier)',
        ]))->output();
    }

    /** A quotation until the customer accepts it, a sales order after. */
    public function salesOrder(SalesOrder $order): string
    {
        $order->loadMissing(['customer', 'company', 'createdBy', 'lines']);

        return Pdf::loadView('print.order', $this->data($order, $this->salesOrderOptions($order)))->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function salesOrderOptions(SalesOrder $order): array
    {
        $quotation = in_array($order->status, ['draft', 'sent'], true);

        return [
            'title' => $quotation ? 'QUOTATION' : 'SALES ORDER',
            'partyLabel' => 'Customer',
            'party' => $order->customer,
            'secondDateLabel' => 'Valid until',
            'secondDate' => $order->expiry_date?->format('M j, Y'),
            'acknowledgeLabel' => 'Conforme (customer)',
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function data(PurchaseOrder|SalesOrder $order, array $extra): array
    {
        $taxCodes = TaxCode::query()->withoutGlobalScopes()->where('company_id', $order->company_id)->pluck('code', 'id');
        $total = 0;
        $lines = [];

        foreach ($order->lines as $line) {
            /** @var PurchaseOrderLine|SalesOrderLine $line */
            $amount = Quantity::extend((int) $line->unit_price, Quantity::toUnits((string) $line->qty));
            $total += $amount;
            $lines[] = [
                'description' => $line->description,
                'qty' => rtrim(rtrim(Quantity::fromUnits(Quantity::toUnits((string) $line->qty)), '0'), '.'),
                'unit_price' => Money::of((int) $line->unit_price)->format(),
                'tax' => $taxCodes[$line->tax_code_id] ?? '',
                'amount' => Money::of($amount)->format(),
            ];
        }

        return $extra + [
            'order' => $order,
            'company' => $order->company,
            'lines' => $lines,
            'total' => Money::of($total)->format(),
            'pricing' => $order->pricing_mode === 'vat_inclusive' ? 'VAT inclusive' : 'VAT exclusive',
            'words' => AmountInWords::pesos($total),
        ];
    }
}
