<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

/**
 * A stocked item's cost can only be debited to its own inventory account;
 * anywhere else, the stock subledger and the ledger drift apart (§9).
 */
final class InventoryAccountException extends LedgerException
{
    public static function mismatch(int $lineNo, string $item, string $inventoryAccount, string $chosenAccount): self
    {
        return new self("Line {$lineNo}: {$item} is a stocked item, so its cost must go to {$inventoryAccount}, not {$chosenAccount}.");
    }

    public static function missing(int $lineNo, string $item): self
    {
        return new self("Line {$lineNo}: {$item} is a stocked item but has no inventory account. Set one on the item first.");
    }
}
