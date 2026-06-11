<?php

declare(strict_types=1);

use App\Actions\Banking\CompleteReconciliation;
use App\Actions\Banking\RecordBankCharge;
use App\Actions\Banking\RecordDeposit;
use App\Actions\Banking\RecordTransfer;
use App\Actions\Banking\StartReconciliation;
use App\Data\Banking\BankChargeData;
use App\Data\Banking\DepositData;
use App\Data\Banking\TransferData;
use App\Models\BankAccount;
use App\Models\PeriodBalance;
use App\Services\Banking\BankBalanceService;

beforeEach(function () {
    $this->company = makeCompany();
    $this->bankAccount = BankAccount::factory()->create([
        'company_id' => $this->company->id,
        'account_id' => account($this->company, '1120')->id,
    ]);
});

it('derives the bank balance from the GL, never storing it', function () {
    // Deposit 100,000 from cash on hand.
    app(RecordDeposit::class)->handle(DepositData::from([
        'company_id' => $this->company->id,
        'bank_account_id' => account($this->company, '1120')->id,
        'source_account_id' => account($this->company, '1110')->id,
        'date' => '2026-06-05',
        'amount' => 100_000_00,
    ]));

    // Bank charge 500.
    app(RecordBankCharge::class)->handle(BankChargeData::from([
        'company_id' => $this->company->id,
        'bank_account_id' => account($this->company, '1120')->id,
        'expense_account_id' => account($this->company, '6200')->id,
        'date' => '2026-06-06',
        'amount' => 500_00,
    ]));

    $derived = app(BankBalanceService::class)->currentBalance($this->bankAccount);
    expect($derived)->toBe(99_500_00);

    // Matches the GL period_balances closing for 1120.
    $period6 = $this->company->periods()->where('period_no', 6)->value('id');
    $closing = PeriodBalance::query()->where('account_id', account($this->company, '1120')->id)
        ->where('period_id', $period6)->first()->closing->minor;
    expect($closing)->toBe(99_500_00);
});

it('transfers between accounts and rejects same-account transfers', function () {
    app(RecordDeposit::class)->handle(DepositData::from([
        'company_id' => $this->company->id,
        'bank_account_id' => account($this->company, '1120')->id,
        'source_account_id' => account($this->company, '1110')->id,
        'date' => '2026-06-05', 'amount' => 100_000_00,
    ]));

    app(RecordTransfer::class)->handle(TransferData::from([
        'company_id' => $this->company->id,
        'from_account_id' => account($this->company, '1120')->id,
        'to_account_id' => account($this->company, '1110')->id,
        'date' => '2026-06-07', 'amount' => 30_000_00,
    ]));

    expect(app(BankBalanceService::class)->currentBalance($this->bankAccount))->toBe(70_000_00);

    expect(fn () => app(RecordTransfer::class)->handle(TransferData::from([
        'company_id' => $this->company->id,
        'from_account_id' => account($this->company, '1120')->id,
        'to_account_id' => account($this->company, '1120')->id,
        'date' => '2026-06-07', 'amount' => 1_00,
    ])))->toThrow(RuntimeException::class);
});

it('completes a reconciliation only at zero difference', function () {
    app(RecordDeposit::class)->handle(DepositData::from([
        'company_id' => $this->company->id,
        'bank_account_id' => account($this->company, '1120')->id,
        'source_account_id' => account($this->company, '1110')->id,
        'date' => '2026-06-05', 'amount' => 100_000_00,
    ]));

    // Wrong statement balance -> cannot complete.
    $bad = app(StartReconciliation::class)->handle($this->bankAccount, '2026-06-30', 90_000_00);
    expect(fn () => app(CompleteReconciliation::class)->handle($bad))->toThrow(RuntimeException::class);

    // Correct statement balance -> completes.
    $good = app(StartReconciliation::class)->handle($this->bankAccount, '2026-06-30', 100_000_00);
    $completed = app(CompleteReconciliation::class)->handle($good);
    expect($completed->status)->toBe('completed')
        ->and($completed->completed_at)->not->toBeNull();
});

it('does not balance when an item is left uncleared', function () {
    app(RecordDeposit::class)->handle(DepositData::from([
        'company_id' => $this->company->id,
        'bank_account_id' => account($this->company, '1120')->id,
        'source_account_id' => account($this->company, '1110')->id,
        'date' => '2026-06-05', 'amount' => 100_000_00,
    ]));

    $rec = app(StartReconciliation::class)->handle($this->bankAccount, '2026-06-30', 100_000_00);
    // Uncheck the deposit line.
    $rec->items()->update(['is_cleared' => false]);

    expect(fn () => app(CompleteReconciliation::class)->handle($rec->fresh()))->toThrow(RuntimeException::class);
});
