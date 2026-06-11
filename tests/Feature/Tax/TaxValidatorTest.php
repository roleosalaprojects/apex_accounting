<?php

declare(strict_types=1);

use App\Enums\TaxpayerType;
use App\Exceptions\Ledger\InvalidVatBucketException;
use App\Models\TaxCode;
use App\Services\Tax\TaxValidator;

beforeEach(function () {
    $this->company = makeCompany();
    $this->validator = new TaxValidator;
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->first();
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->first();
});

it('rejects VAT12 on a VAT-exempt (rice) line', function () {
    $this->validator->assertAllowed($this->vat12, TaxpayerType::Vat, lineIsVatExempt: true);
})->throws(InvalidVatBucketException::class);

it('rejects VAT12 for a non-VAT company', function () {
    $this->validator->assertAllowed($this->vat12, TaxpayerType::NonVat);
})->throws(InvalidVatBucketException::class);

it('allows VAT12 for a VAT company on a non-exempt line', function () {
    $this->validator->assertAllowed($this->vat12, TaxpayerType::Vat, lineIsVatExempt: false);
    expect(true)->toBeTrue();
});

it('always allows EXEMPT', function () {
    $this->validator->assertAllowed($this->exempt, TaxpayerType::NonVat, lineIsVatExempt: true);
    expect(true)->toBeTrue();
});
