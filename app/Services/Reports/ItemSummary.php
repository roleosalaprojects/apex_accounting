<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Item;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Key figures for an item's page: stock on hand at weighted-average cost, and
 * the quantities and amounts (net of VAT) sold and bought in the period.
 */
final class ItemSummary
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return array{on_hand: array{qty_units: int, avg_cost_x10000: int, value: int}, sold_units: int, sales: int, bought_units: int, purchases: int}
     */
    public function build(Item $item, string $from, string $asOf): array
    {
        $posted = [InvoiceStatus::Posted->value, InvoiceStatus::PartiallyPaid->value, InvoiceStatus::Paid->value];

        $sold = DB::table('invoice_lines')
            ->join('invoices', 'invoice_lines.invoice_id', '=', 'invoices.id')
            ->where('invoices.company_id', $item->company_id)
            ->where('invoice_lines.item_id', $item->id)
            ->whereIn('invoices.status', $posted)
            ->whereDate('invoices.invoice_date', '>=', $from)
            ->whereDate('invoices.invoice_date', '<=', $asOf)
            ->get(['invoice_lines.qty', 'invoice_lines.line_total']);

        $bought = DB::table('bill_lines')
            ->join('bills', 'bill_lines.bill_id', '=', 'bills.id')
            ->where('bills.company_id', $item->company_id)
            ->where('bill_lines.item_id', $item->id)
            ->whereIn('bills.status', $posted)
            ->whereDate('bills.bill_date', '>=', $from)
            ->whereDate('bills.bill_date', '<=', $asOf)
            ->get(['bill_lines.qty', 'bill_lines.line_total']);

        return [
            'on_hand' => $this->inventory->onHand($item),
            'sold_units' => (int) $sold->sum(fn (stdClass $line): int => Quantity::toUnits((string) $line->qty)),
            'sales' => (int) $sold->sum('line_total'),
            'bought_units' => (int) $bought->sum(fn (stdClass $line): int => Quantity::toUnits((string) $line->qty)),
            'purchases' => (int) $bought->sum('line_total'),
        ];
    }
}
