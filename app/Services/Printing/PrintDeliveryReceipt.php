<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Models\Delivery;
use App\Models\DeliveryLine;
use App\Support\Quantity;
use Barryvdh\DomPDF\Facade\Pdf;

/** The delivery receipt that travels with the goods: what went out, against which order, and who received it. */
final class PrintDeliveryReceipt
{
    public function render(Delivery $delivery): string
    {
        return Pdf::loadView('print.delivery', $this->data($delivery))->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Delivery $delivery): array
    {
        $delivery->loadMissing(['company', 'salesOrder.customer', 'salesOrder.lines', 'createdBy', 'lines']);
        $orderLines = $delivery->salesOrder->lines->keyBy('id');

        $lines = [];
        foreach ($delivery->lines as $line) {
            /** @var DeliveryLine $line */
            $ordered = $orderLines->get($line->sales_order_line_id);
            $lines[] = [
                'description' => $line->description,
                'qty' => Quantity::compact(Quantity::toUnits($line->qty)),
                'ordered' => $ordered !== null ? Quantity::compact(Quantity::toUnits($ordered->qty)) : '',
                'delivered_to_date' => $ordered !== null ? Quantity::compact(Quantity::toUnits($ordered->delivered_qty)) : '',
            ];
        }

        return [
            'delivery' => $delivery,
            'order' => $delivery->salesOrder,
            'company' => $delivery->company,
            'customer' => $delivery->salesOrder->customer,
            'lines' => $lines,
        ];
    }
}
