<?php

declare(strict_types=1);

use App\Services\Assets\DepreciationSchedule;

beforeEach(function () {
    $this->schedule = new DepreciationSchedule;
});

it('sums exactly to the depreciable base with the remainder in the final month', function () {
    // ₱120,000 over 36 months (golden master motorcycle).
    $schedule = $this->schedule->fullSchedule(120_000_00, 36);

    expect($schedule[0])->toBe(3_333_33)            // first month ₱3,333.33
        ->and(array_sum($schedule))->toBe(120_000_00) // sums exactly
        ->and($schedule[35])->toBe(3_333_45)        // final month absorbs the remainder
        ->and(count($schedule))->toBe(36);
});

it('returns zero once fully depreciated', function () {
    expect($this->schedule->amountForEntry(900_00, 3, 4))->toBe(0)
        ->and($this->schedule->amountForEntry(900_00, 3, 1))->toBe(300_00)
        ->and($this->schedule->amountForEntry(900_00, 3, 3))->toBe(300_00);
});
