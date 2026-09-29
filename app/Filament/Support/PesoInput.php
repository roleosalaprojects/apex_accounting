<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/**
 * A peso amount field for a centavo column (Money cast or plain integer): the
 * form shows and takes pesos, the model receives centavos.
 *
 * Numeric by type and rule rather than ->numeric(), whose float state cast
 * runs before formatStateUsing and cannot read a Money.
 */
final class PesoInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->type('number')
            ->step('0.01')
            ->inputMode('decimal')
            ->rule('numeric')
            ->prefix('₱')
            ->formatStateUsing(fn (mixed $state): mixed => match (true) {
                $state instanceof Money => $state->toDecimal(),
                is_int($state) => Money::of($state)->toDecimal(),
                default => $state,
            })
            ->dehydrateStateUsing(fn (mixed $state): ?int => blank($state) ? null : (int) round((float) $state * 100));
    }
}
