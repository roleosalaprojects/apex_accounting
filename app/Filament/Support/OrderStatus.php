<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Support\Quantity;

/** Labels, colours and fulfilment summaries for sales and purchase orders. */
final class OrderStatus
{
    public const SALES = [
        'draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted',
        'partially_invoiced' => 'Partially invoiced', 'invoiced' => 'Invoiced', 'cancelled' => 'Cancelled',
    ];

    public const PURCHASE = [
        'draft' => 'Draft', 'sent' => 'Sent', 'received' => 'Received',
        'partially_billed' => 'Partially billed', 'billed' => 'Billed', 'cancelled' => 'Cancelled',
    ];

    public static function label(string $status): string
    {
        return self::SALES[$status] ?? self::PURCHASE[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    public static function color(string $status): string
    {
        return match ($status) {
            'invoiced', 'billed' => 'success',
            'partially_invoiced', 'partially_billed' => 'warning',
            'accepted', 'received' => 'info',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /** "6 / 12 · 6 / 12": units delivered and invoiced against units ordered. */
    public static function salesProgress(SalesOrder $order): string
    {
        $ordered = $delivered = $invoiced = 0;
        foreach ($order->lines as $line) {
            /** @var SalesOrderLine $line */
            $ordered += Quantity::toUnits($line->qty);
            $delivered += Quantity::toUnits($line->delivered_qty);
            $invoiced += Quantity::toUnits($line->invoiced_qty);
        }

        return Quantity::compact($delivered).' / '.Quantity::compact($ordered).' · '.Quantity::compact($invoiced).' / '.Quantity::compact($ordered);
    }

    /** "4 / 10": units billed against units ordered. */
    public static function purchaseProgress(PurchaseOrder $order): string
    {
        $ordered = $billed = 0;
        foreach ($order->lines as $line) {
            /** @var PurchaseOrderLine $line */
            $ordered += Quantity::toUnits($line->qty);
            $billed += Quantity::toUnits($line->billed_qty);
        }

        return Quantity::compact($billed).' / '.Quantity::compact($ordered);
    }
}
