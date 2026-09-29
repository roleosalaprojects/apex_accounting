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
 * Each item also carries the exact value of its stock, which moves by exactly
 * what is posted to its inventory account: a receipt adds its cost, an issue
 * removes the cost of sales it posts, and the last unit out takes whatever
 * value is left. The stock subledger therefore equals the ledger to the
 * centavo, however awkward the averages.
 */
final class InventoryService
{
    // avg stored as centavos × 10000; value divisor = qty-scale (1e4) × cost-scale (1e4)
    private const VALUE_DIVISOR = 100_000_000;

    public function __construct(private readonly VatMath $vat) {}

    /**
     * Receive stock: add its cost to the value and recompute the average.
     *   new_avg = (old_value + recv_cost) / (old_qty + recv_qty)
     */
    public function receive(Item $item, int $recvQtyUnits, int $recvTotalCost): ItemValuation
    {
        $valuation = $this->valuationFor($item);

        $newQty = $valuation->qty_units + $recvQtyUnits;
        $newValue = $valuation->value + $recvTotalCost;

        $newAvg = $newQty > 0
            ? $this->vat->roundDiv($newValue * self::VALUE_DIVISOR, $newQty)
            : 0;

        $valuation->forceFill([
            'qty_units' => $newQty,
            'avg_cost_x10000' => $newAvg,
            'value' => $newValue,
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

        // Issuing the last unit takes whatever value is left, so rounding at the
        // average never strands centavos in the inventory account.
        $cogs = $newQty === 0
            ? $valuation->value
            : $this->valueOf($issueQtyUnits, $valuation->avg_cost_x10000);

        $valuation->forceFill([
            'qty_units' => $newQty,
            'value' => $valuation->value - $cogs,
        ])->save();

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
        return $this->valuationFor($item)->value;
    }

    /**
     * Stock on hand for display. Unlike the posting paths, never creates the
     * valuation row: an item that has never moved reads as zero.
     *
     * @return array{qty_units: int, avg_cost_x10000: int, value: int}
     */
    public function onHand(Item $item): array
    {
        $valuation = ItemValuation::query()
            ->withoutGlobalScopes()
            ->where('company_id', $item->company_id)
            ->where('item_id', $item->id)
            ->first();

        return [
            'qty_units' => $valuation->qty_units ?? 0,
            'avg_cost_x10000' => $valuation->avg_cost_x10000 ?? 0,
            'value' => $valuation->value ?? 0,
        ];
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
            ['qty_units' => 0, 'avg_cost_x10000' => 0, 'value' => 0],
        );
    }
}
