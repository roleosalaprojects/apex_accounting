<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Exceptions\Ledger\NegativeInventoryException;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemValuation;
use App\Services\Tax\VatMath;

/**
 * Weighted-average inventory valuation (§9). Quantities are integer
 * ten-thousandths; the average cost is stored as centavos × 10000. All maths
 * are integer (no floats in money paths, §16.3).
 *
 *   inventory value (centavos) = qty_units × avg_cost_x10000 / 1e8
 */
final class InventoryService
{
    // avg stored as centavos × 10000; value divisor = qty-scale (1e4) × cost-scale (1e4)
    private const VALUE_DIVISOR = 100_000_000;

    public function __construct(private readonly VatMath $vat) {}

    /**
     * Receive stock: recompute the weighted average.
     *   new_avg = (old_qty × old_avg + recv_qty × recv_cost) / (old_qty + recv_qty)
     */
    public function receive(Item $item, int $recvQtyUnits, int $recvTotalCost): ItemValuation
    {
        $valuation = $this->valuationFor($item);

        $oldValue = $this->valueOf($valuation->qty_units, $valuation->avg_cost_x10000);
        $newQty = $valuation->qty_units + $recvQtyUnits;
        $newValue = $oldValue + $recvTotalCost;

        $newAvg = $newQty > 0
            ? $this->vat->roundDiv($newValue * self::VALUE_DIVISOR, $newQty)
            : 0;

        $valuation->forceFill([
            'qty_units' => $newQty,
            'avg_cost_x10000' => $newAvg,
        ])->save();

        return $valuation;
    }

    /**
     * Issue stock at the current weighted average; returns the COGS (centavos).
     * Blocks driving stock negative when the company flag is set (§16, §9).
     */
    public function issue(Item $item, int $issueQtyUnits, Company $company): int
    {
        $valuation = $this->valuationFor($item);

        $newQty = $valuation->qty_units - $issueQtyUnits;
        if ($newQty < 0 && $company->block_negative_inventory) {
            throw NegativeInventoryException::make("item {$item->sku}");
        }

        $cogs = $this->valueOf($issueQtyUnits, $valuation->avg_cost_x10000);

        $valuation->forceFill(['qty_units' => $newQty])->save();

        return $cogs;
    }

    public function currentQtyUnits(Item $item): int
    {
        return $this->valuationFor($item)->qty_units;
    }

    public function currentAvgX10000(Item $item): int
    {
        return $this->valuationFor($item)->avg_cost_x10000;
    }

    public function valueAtCurrentAverage(Item $item, int $qtyUnits): int
    {
        return $this->valueOf($qtyUnits, $this->valuationFor($item)->avg_cost_x10000);
    }

    public function inventoryValue(Item $item): int
    {
        $valuation = $this->valuationFor($item);

        return $this->valueOf($valuation->qty_units, $valuation->avg_cost_x10000);
    }

    private function valueOf(int $qtyUnits, int $avgX10000): int
    {
        if ($qtyUnits === 0) {
            return 0;
        }

        return $this->vat->roundDiv($qtyUnits * $avgX10000, self::VALUE_DIVISOR);
    }

    private function valuationFor(Item $item): ItemValuation
    {
        return ItemValuation::query()->firstOrCreate(
            ['company_id' => $item->company_id, 'item_id' => $item->id],
            ['qty_units' => 0, 'avg_cost_x10000' => 0],
        );
    }
}
