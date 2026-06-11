<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Models\Company;
use App\Models\InventoryAdjustment;
use App\Models\Item;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Count correction for an inventory item (§9): adjusts the running quantity and
 * posts Dr/Cr inventory against an adjustment account.
 *
 *  - increase: receive the delta at the supplied unit cost; Dr inventory / Cr adj
 *  - decrease: issue the delta at the current average;       Dr adj / Cr inventory
 */
final class AdjustInventory
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly InventoryService $inventory,
    ) {}

    public function handle(
        Item $item,
        string $date,
        string $qtyChange,
        int $adjustmentAccountId,
        ?int $unitCost = null,
        ?string $reason = null,
        ?User $actor = null,
    ): InventoryAdjustment {
        if ($item->inventory_account_id === null) {
            throw new RuntimeException("Item {$item->sku} has no inventory account.");
        }

        return DB::transaction(function () use ($item, $date, $qtyChange, $adjustmentAccountId, $unitCost, $reason, $actor): InventoryAdjustment {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($item->company_id);

            $deltaUnits = Quantity::toUnits($qtyChange);

            if ($deltaUnits > 0) {
                $cost = $unitCost !== null
                    ? Quantity::extend($unitCost, $deltaUnits)
                    : $this->inventory->valueAtCurrentAverage($item, $deltaUnits);
                $this->inventory->receive($item, $deltaUnits, $cost);
                $valueChange = $cost;
            } else {
                $valueChange = -$this->inventory->issue($item, -$deltaUnits, $company);
            }

            $lines = $valueChange >= 0
                ? [
                    new JournalLineData(account_id: $item->inventory_account_id, debit: $valueChange, memo: 'Inventory count up'),
                    new JournalLineData(account_id: $adjustmentAccountId, credit: $valueChange, memo: 'Inventory adjustment'),
                ]
                : [
                    new JournalLineData(account_id: $adjustmentAccountId, debit: -$valueChange, memo: 'Inventory adjustment'),
                    new JournalLineData(account_id: $item->inventory_account_id, credit: -$valueChange, memo: 'Inventory count down'),
                ];

            $entry = $this->post->handle(new JournalEntryData(
                company_id: $company->id,
                entry_date: $date,
                memo: 'Inventory adjustment — '.$item->sku,
                lines: new DataCollection(JournalLineData::class, $lines),
                created_by: $actor?->id,
                approved_by: $actor?->id,
            ), $actor);

            return InventoryAdjustment::query()->create([
                'company_id' => $company->id,
                'item_id' => $item->id,
                'adjustment_date' => $date,
                'qty_units_change' => $deltaUnits,
                'value_change' => $valueChange,
                'journal_entry_id' => $entry->id,
                'reason' => $reason,
                'created_by' => $actor?->id,
            ]);
        });
    }
}
