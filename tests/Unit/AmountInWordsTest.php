<?php

declare(strict_types=1);

use App\Support\AmountInWords;

it('spells peso amounts the way checks and receipts do', function (int $minor, string $words) {
    expect(AmountInWords::pesos($minor))->toBe($words);
})->with([
    [0, 'ZERO PESOS AND 00/100'],
    [1_00, 'ONE PESO AND 00/100'],
    [1_50, 'ONE PESO AND 50/100'],
    [5, 'ZERO PESOS AND 05/100'],
    [21_00, 'TWENTY-ONE PESOS AND 00/100'],
    [100_00, 'ONE HUNDRED PESOS AND 00/100'],
    [1_010_00, 'ONE THOUSAND TEN PESOS AND 00/100'],
    [53_500_00, 'FIFTY-THREE THOUSAND FIVE HUNDRED PESOS AND 00/100'],
    [123_456_78, 'ONE HUNDRED TWENTY-THREE THOUSAND FOUR HUNDRED FIFTY-SIX PESOS AND 78/100'],
    [1_310_000_00, 'ONE MILLION THREE HUNDRED TEN THOUSAND PESOS AND 00/100'],
    [2_000_000_000_00, 'TWO BILLION PESOS AND 00/100'],
    [-15_25, 'MINUS FIFTEEN PESOS AND 25/100'],
]);
