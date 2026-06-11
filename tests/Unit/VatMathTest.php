<?php

declare(strict_types=1);

use App\Services\Tax\VatMath;

beforeEach(function () {
    $this->vat = new VatMath;
});

it('splits 12% VAT-inclusive amounts exactly at boundary values', function () {
    // ₱112.00 inclusive -> base ₱100.00, VAT ₱12.00
    $r = $this->vat->fromInclusive(112_00, 1200);
    expect($r->base)->toBe(100_00)->and($r->vat)->toBe(12_00);

    // ₱1.00 inclusive -> base round(100*10000/11200)=89, VAT 11
    $r = $this->vat->fromInclusive(1_00, 1200);
    expect($r->base)->toBe(89)->and($r->vat)->toBe(11)->and($r->total())->toBe(1_00);

    // ₱0.01 inclusive -> base round(1*10000/11200)=1 (0.89 -> 1), VAT 0
    $r = $this->vat->fromInclusive(1, 1200);
    expect($r->base)->toBe(1)->and($r->vat)->toBe(0)->and($r->total())->toBe(1);

    // Large: ₱1,000,000.00 inclusive
    $r = $this->vat->fromInclusive(1_000_000_00, 1200);
    expect($r->total())->toBe(1_000_000_00)
        ->and($r->base + $r->vat)->toBe(1_000_000_00);
});

it('computes VAT-exclusive amounts', function () {
    $r = $this->vat->fromExclusive(100_00, 1200);
    expect($r->base)->toBe(100_00)->and($r->vat)->toBe(12_00);

    // 200,000 base -> 24,000 VAT (golden master B-2)
    $r = $this->vat->fromExclusive(200_000_00, 1200);
    expect($r->vat)->toBe(24_000_00);
});

it('treats exempt and zero rated as zero VAT', function () {
    expect($this->vat->fromInclusive(56_000_00, 0)->vat)->toBe(0)
        ->and($this->vat->fromExclusive(56_000_00, 0)->vat)->toBe(0);
});

it('rounds half up to the centavo', function () {
    // 12.5 -> 13
    expect($this->vat->roundDiv(25, 2))->toBe(13)
        ->and($this->vat->roundDiv(24, 2))->toBe(12)
        ->and($this->vat->roundDiv(23, 2))->toBe(12); // 11.5 -> 12
});
