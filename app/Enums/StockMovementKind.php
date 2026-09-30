<?php

declare(strict_types=1);

namespace App\Enums;

/** Why stock moved: the document behind each line of the stock ledger. */
enum StockMovementKind: string
{
    case Receipt = 'receipt';
    case Issue = 'issue';
    case ReturnOut = 'return_out';
    case Adjustment = 'adjustment';
    case VoidIn = 'void_in';
    case VoidOut = 'void_out';

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Issue => 'Sale',
            self::ReturnOut => 'Return to vendor',
            self::Adjustment => 'Count adjustment',
            self::VoidIn => 'Voided sale',
            self::VoidOut => 'Voided receipt',
        };
    }
}
