<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\ItemType;
use App\Models\Item;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Bill and purchase-order lines: picking an item fills in its name and
 * purchase price (unless already typed) and, for a stocked item, its inventory
 * account, which PostBill requires (§9) and the form then locks.
 */
final class PurchaseLineDefaults
{
    public static function apply(mixed $itemId, Get $get, Set $set, string $accountField): void
    {
        $item = self::item($itemId);
        if ($item === null) {
            return;
        }

        if (self::locksAccount($itemId)) {
            $set($accountField, $item->inventory_account_id);
        }

        if (blank($get('description'))) {
            $set('description', $item->name);
        }

        if ((blank($get('unit_price')) || (float) $get('unit_price') === 0.0) && $item->default_purchase_price->isPositive()) {
            $set('unit_price', $item->default_purchase_price->toDecimal());
        }
    }

    /** A stocked item with an inventory account can only be costed there. */
    public static function locksAccount(mixed $itemId): bool
    {
        $item = self::item($itemId);

        return $item !== null && $item->type === ItemType::Inventory && $item->inventory_account_id !== null;
    }

    private static function item(mixed $itemId): ?Item
    {
        if (blank($itemId)) {
            return null;
        }

        return once(fn (): ?Item => Item::query()->find((int) $itemId));
    }
}
