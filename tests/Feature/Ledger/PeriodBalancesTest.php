<?php

declare(strict_types=1);

use App\Actions\Ledger\ReverseJournalEntry;
use App\Models\PeriodBalance;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->company = makeCompany();
});

it('maintains period_balances that ledger:verify confirms against journal_lines', function () {
    postEntry($this->company, '2026-06-01', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 1_000_000_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 1_000_000_00],
    ]);

    postEntry($this->company, '2026-06-05', [
        ['account_id' => account($this->company, '6100')->id, 'debit' => 50_000_00],
        ['account_id' => account($this->company, '1120')->id, 'credit' => 50_000_00],
    ]);

    $cashClosing = PeriodBalance::query()
        ->where('account_id', account($this->company, '1120')->id)
        ->where('period_id', $this->company->periods()->where('period_no', 6)->first()->id)
        ->first();

    expect($cashClosing->closing->minor)->toBe(950_000_00)
        ->and($cashClosing->debits->minor)->toBe(1_000_000_00)
        ->and($cashClosing->credits->minor)->toBe(50_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('carries closing balances forward across periods', function () {
    postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 200_000_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 200_000_00],
    ]);

    // July (period 7) should open with June's closing even with no July activity.
    $julyId = $this->company->periods()->where('period_no', 7)->first()->id;
    $julyCash = PeriodBalance::query()
        ->where('account_id', account($this->company, '1120')->id)
        ->where('period_id', $julyId)->first();

    expect($julyCash->opening->minor)->toBe(200_000_00)
        ->and($julyCash->closing->minor)->toBe(200_000_00);

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('ledger:verify still passes after a reversal', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100_000_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100_000_00],
    ]);

    app(ReverseJournalEntry::class)->handle($entry, 'correction');

    expect(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});
