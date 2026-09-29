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

    /** For table columns: a Money, or centavos as the database returns them; blank stays blank. */
    public static function state(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        return self::format($state instanceof Money ? $state : (int) $state);
    }

    /** Compact figure for dashboard tiles: ₱84,213 · ₱842K · ₱1.75M · ₱19.0M. */
    public static function compact(int $minor): string
    {
        $pesos = abs($minor) / 100;

        $body = match (true) {
            $pesos >= 999_500_000 => number_format($pesos / 1_000_000_000, 2).'B',
            $pesos >= 9_995_000 => number_format($pesos / 1_000_000, 1).'M',
            $pesos >= 999_500 => number_format($pesos / 1_000_000, 2).'M',
            $pesos >= 100_000 => number_format($pesos / 1_000).'K',
            default => number_format($pesos),
        };

        return ($minor < 0 ? '−' : '').'₱'.$body;
    }
}
