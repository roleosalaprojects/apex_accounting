<?php

declare(strict_types=1);

use App\Support\Money;

it('does integer-centavo arithmetic without floats', function () {
    $a = Money::of(11250); // 112.50
    $b = Money::of(10000); // 100.00

    expect($a->plus($b)->minor)->toBe(21250)
        ->and($a->minus($b)->minor)->toBe(1250)
        ->and($b->times(3)->minor)->toBe(30000)
        ->and($a->negate()->minor)->toBe(-11250)
        ->and($a->abs()->minor)->toBe(11250);
});

it('formats and compares correctly', function () {
    expect(Money::of(100000000)->toDecimal())->toBe('1000000.00')
        ->and(Money::of(-5350)->toDecimal())->toBe('-53.50')
        ->and(Money::of(1)->toDecimal())->toBe('0.01')
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::of(-1)->isNegative())->toBeTrue()
        ->and(Money::of(500)->compareTo(Money::of(400)))->toBe(1)
        ->and(Money::of(400)->equals(Money::of(400)))->toBeTrue();
});

it('rejects currency mismatch', function () {
    Money::of(100, 'PHP')->plus(Money::of(100, 'USD'));
})->throws(InvalidArgumentException::class);
