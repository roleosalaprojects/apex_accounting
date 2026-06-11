<?php

declare(strict_types=1);

use App\Actions\Ledger\CloseFiscalYear;
use App\Enums\PeriodStatus;
use App\Models\PeriodBalance;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
});

it('closes the fiscal year, zeroing nominal accounts into retained earnings', function () {
    // Revenue 100k, expense 30k -> net income 70k.
    postEntry($this->company, '2026-03-10', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100_000_00],
        ['account_id' => account($this->company, '4200')->id, 'credit' => 100_000_00],
    ]);
    postEntry($this->company, '2026-04-10', [
        ['account_id' => account($this->company, '6100')->id, 'debit' => 30_000_00],
        ['account_id' => account($this->company, '1120')->id, 'credit' => 30_000_00],
    ]);

    app(CloseFiscalYear::class)->handle($this->company->fresh(), 2026);

    $lastPeriodId = $this->company->periods()->where('period_no', 12)->first()->id;

    $income = PeriodBalance::query()
        ->where('account_id', account($this->company, '4200')->id)
        ->where('period_id', $lastPeriodId)->first();
    $expense = PeriodBalance::query()
        ->where('account_id', account($this->company, '6100')->id)
        ->where('period_id', $lastPeriodId)->first();
    $retained = PeriodBalance::query()
        ->where('account_id', account($this->company, '3900')->id)
        ->where('period_id', $lastPeriodId)->first();

    // Nominal accounts zeroed; retained earnings holds the 70k net income (credit).
    expect($income->closing->minor)->toBe(0)
        ->and($expense->closing->minor)->toBe(0)
        ->and($retained->closing->minor)->toBe(-70_000_00);

    // All periods of the year are now locked.
    expect($this->company->periods()->where('fiscal_year', 2026)->where('status', '!=', PeriodStatus::Locked->value)->count())->toBe(0);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});
