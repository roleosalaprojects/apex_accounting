<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Item;
use App\Models\StockMovement;
use App\Services\Tax\VatMath;
use Illuminate\Support\Facades\DB;

/**
 * Stock card (§9): one item's movements over a period with a running balance
 * — the opening quantity and value, each receipt or issue with the document
 * behind it, and the closing balance. Read straight off the stock ledger.
 */
final class StockCardReport
{
    public function __construct(private readonly VatMath $math) {}

    /**
     * @return array{item: Item, opening: array{qty_units: int, value: int}, rows: list<array<string, mixed>>, closing: array{qty_units: int, value: int}}
     */
    public function build(Item $item, string $from, string $asOf): array
    {
        $opening = DB::table('stock_movements')
            ->where('company_id', $item->company_id)->where('item_id', $item->id)
            ->whereDate('moved_on', '<', $from)
            ->selectRaw('COALESCE(SUM(qty_units), 0) as units, COALESCE(SUM(value), 0) as value')
            ->first();
        $units = (int) ($opening->units ?? 0);
        $value = (int) ($opening->value ?? 0);
        $openingBalance = ['qty_units' => $units, 'value' => $value];

        $rows = [];
        $movements = StockMovement::query()->withoutGlobalScopes()
            ->where('company_id', $item->company_id)->where('item_id', $item->id)
            ->whereDate('moved_on', '>=', $from)->whereDate('moved_on', '<=', $asOf)
            ->orderBy('moved_on')->orderBy('id')->get();

        foreach ($movements as $movement) {
            $units += $movement->qty_units;
            $value += $movement->value;
            $rows[] = [
                'date' => $movement->moved_on->toDateString(),
                'kind' => $movement->kind->label(),
                'reference' => $movement->reference,
                'description' => $movement->description,
                'in_units' => max(0, $movement->qty_units),
                'out_units' => max(0, -$movement->qty_units),
                'value' => $movement->value,
                'unit_cost' => $this->unitCost($movement->qty_units, $movement->value),
                'balance_units' => $units,
                'balance_value' => $value,
                'source_type' => $movement->source_type,
                'source_id' => $movement->source_id,
            ];
        }

        return [
            'item' => $item,
            'opening' => $openingBalance,
            'rows' => $rows,
            'closing' => ['qty_units' => $units, 'value' => $value],
        ];
    }

    /** Centavos per unit of what moved; zero when nothing moved. */
    public function unitCost(int $qtyUnits, int $value): int
    {
        if ($qtyUnits === 0) {
            return 0;
        }

        return $this->math->roundDiv(abs($value) * 10_000, abs($qtyUnits));
    }
}
