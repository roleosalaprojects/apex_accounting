<?php

declare(strict_types=1);

use App\Filament\Support\Peso;

it('formats compact peso figures for dashboard tiles', function () {
    expect(Peso::compact(84_213_00))->toBe('₱84,213')
        ->and(Peso::compact(842_300_00))->toBe('₱842K')
        ->and(Peso::compact(999_600_00))->toBe('₱1.00M')
        ->and(Peso::compact(1_754_000_00))->toBe('₱1.75M')
        ->and(Peso::compact(19_020_000_00))->toBe('₱19.0M')
        ->and(Peso::compact(-1_440_000_00))->toBe('−₱1.44M');
});
