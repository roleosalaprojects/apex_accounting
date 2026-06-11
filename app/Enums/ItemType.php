<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case Inventory = 'inventory';
    case NonInventory = 'non_inventory';
    case Service = 'service';

    public function tracksStock(): bool
    {
        return $this === self::Inventory;
    }
}
