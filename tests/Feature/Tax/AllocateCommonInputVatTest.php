<?php

declare(strict_types=1);

use App\Actions\Tax\AllocateCommonInputVat;
use App\Exceptions\Ledger\DuplicateAllocationException;
use App\Models\PeriodBalance;
use App\Models\TaxCode;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
    $this->exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
});

it('allocates common input VAT by the quarterly sales ratio (12k / 48k split)', function () {
    // Exempt rice sales ₱4,000,000.
    postEntry($this->company, '2026-06-10', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 4_000_000_00],
        ['account_id' => account($this->company, '4100')->id, 'credit' => 4_000_000_00, 'tax_code_id' => $this->exempt],
    ]);

    // VATable POS sales ₱1,000,000 (net of VAT).
    postEntry($this->company, '2026-06-12', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 1_000_000_00],
        ['account_id' => account($this->company, '4200')->id, 'credit' => 1_000_000_00, 'tax_code_id' => $this->vat12],
    ]);

    // Overhead (rent) carrying ₱60,000 common input VAT into 1410.
    postEntry($this->company, '2026-06-05', [
        ['account_id' => account($this->company, '6100')->id, 'debit' => 500_000_00],
        ['account_id' => account($this->company, '1410')->id, 'debit' => 60_000_00],
        ['account_id' => account($this->company, '1120')->id, 'credit' => 560_000_00],
    ]);

    $allocation = app(AllocateCommonInputVat::class)->handle($this->company->fresh(), 2026, 2);

    expect($allocation->vatable_sales->minor)->toBe(1_000_000_00)
        ->and($allocation->exempt_sales->minor)->toBe(4_000_000_00)
        ->and($allocation->common_input_vat->minor)->toBe(60_000_00)
        ->and($allocation->ratio_creditable_bp)->toBe(2000)
        ->and($allocation->creditable->minor)->toBe(12_000_00)
        ->and($allocation->non_creditable->minor)->toBe(48_000_00)
        ->and($allocation->journal_entry_id)->not->toBeNull();

    // 1410 is fully relieved; 1400 carries the 12k creditable.
    $periodId = $this->company->periods()->where('period_no', 6)->value('id');
    $deferred = PeriodBalance::query()->where('account_id', account($this->company, '1410')->id)
        ->where('period_id', $periodId)->first();
    $inputVat = PeriodBalance::query()->where('account_id', account($this->company, '1400')->id)
        ->where('period_id', $periodId)->first();

    expect($deferred->closing->minor)->toBe(0)
        ->and($inputVat->closing->minor)->toBe(12_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('is idempotent per quarter — re-running throws', function () {
    postEntry($this->company, '2026-06-05', [
        ['account_id' => account($this->company, '6100')->id, 'debit' => 500_000_00],
        ['account_id' => account($this->company, '1410')->id, 'debit' => 60_000_00],
        ['account_id' => account($this->company, '1120')->id, 'credit' => 560_000_00],
    ]);
    postEntry($this->company, '2026-06-12', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 1_000_000_00],
        ['account_id' => account($this->company, '4200')->id, 'credit' => 1_000_000_00, 'tax_code_id' => $this->vat12],
    ]);

    app(AllocateCommonInputVat::class)->handle($this->company->fresh(), 2026, 2);
    app(AllocateCommonInputVat::class)->handle($this->company->fresh(), 2026, 2);
})->throws(DuplicateAllocationException::class);
