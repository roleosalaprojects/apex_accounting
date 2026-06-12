<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Money;

/** Shared peso formatting for infolists and custom pages. */
final class Peso
{
    public static function format(Money|int|null $value): string
    {
        $minor = $value instanceof Money ? $value->minor : ($value ?? 0);

        return '₱'.number_format($minor / 100, 2);
    }
}
