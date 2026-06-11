<?php

declare(strict_types=1);

use App\Actions\Ledger\OpenFiscalYear;
use App\Actions\Ledger\SetupOpeningBalances;
use App\Data\Ledger\OpeningBalancesData;
use App\Models\PeriodBalance;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    // Open the prior year so the cutover JE (dated 2025-12-31) lands in an open period.
    $this->company = makeCompany();
    app(OpenFiscalYear::class)->handle($this->company, 2025);
});

it('posts a balanced opening JE and plugs the residual into Opening Balance Equity', function () {
    $entry = app(SetupOpeningBalances::class)->handle(OpeningBalancesData::from([
        'company_id' => $this->company->id,
        'opening_date' => '2025-12-31',
        'lines' => [
            ['account_id' => account($this->company, '1120')->id, 'debit' => 500_000_00],
            ['account_id' => account($this->company, '1300')->id, 'debit' => 300_000_00],
            // A non-control liability; open AR/AP use opening documents (Phase 3/4), not the GL JE.
            ['account_id' => account($this->company, '2200')->id, 'credit' => 100_000_00],
        ],
    ]));

    // Debits 800k, credits 100k -> 700k plug credited to Opening Balance Equity.
    expect($entry->total_debits->minor)->toBe($entry->total_credits->minor)
        ->and($entry->total_debits->minor)->toBe(800_000_00);

    $obe = account($this->company, '3950');
    $obeBalance = PeriodBalance::query()
        ->where('account_id', $obe->id)
        ->orderByDesc('period_id')->first();

    // OBE carries a 700k credit balance (signed closing negative).
    expect($obeBalance->closing->minor)->toBe(-700_000_00);

    // Trial balance ties out.
    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});
