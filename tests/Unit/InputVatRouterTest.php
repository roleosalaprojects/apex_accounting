<?php

declare(strict_types=1);

use App\Enums\VatBucket;
use App\Services\Tax\InputVatRouter;

beforeEach(function () {
    $this->router = new InputVatRouter;
});

it('routes the three input VAT buckets to the right accounts', function () {
    expect($this->router->accountCodeFor(VatBucket::DirectVatable))->toBe('1400')
        ->and($this->router->accountCodeFor(VatBucket::Common))->toBe('1410')
        ->and($this->router->accountCodeFor(VatBucket::DirectExempt))->toBeNull();
});

it('knows which buckets are creditable now vs capitalized into cost', function () {
    expect($this->router->isCreditableNow(VatBucket::DirectVatable))->toBeTrue()
        ->and($this->router->isCreditableNow(VatBucket::Common))->toBeFalse()
        ->and($this->router->isCapitalizedIntoCost(VatBucket::DirectExempt))->toBeTrue();
});
