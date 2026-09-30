<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

/**
 * A rate stored in basis points (1200 = 12%), entered and shown as a
 * percentage with two decimals.
 */
final class PercentInput
{
    public static function make(string $name = 'rate_bp', string $label = 'Rate (%)'): TextInput
    {
        return TextInput::make($name)->label($label)
            ->numeric()->minValue(0)->maxValue(100)->step(0.01)->suffix('%')
            ->required()
            ->formatStateUsing(fn (int|string|null $state): ?string => $state === null ? null : number_format((int) $state / 100, 2, '.', ''))
            ->dehydrateStateUsing(fn (int|float|string|null $state): int => (int) round((float) $state * 100));
    }

    /** 1200 → "12.00%". */
    public static function format(int $basisPoints): string
    {
        return number_format($basisPoints / 100, 2).'%';
    }
}
