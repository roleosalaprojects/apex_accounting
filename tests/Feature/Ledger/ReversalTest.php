<?php

declare(strict_types=1);

use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\ImmutableEntryException;
use App\Models\PeriodBalance;

beforeEach(function () {
    $this->company = makeCompany();
});

it('reverses a posted entry and nets account balances to zero', function () {
    $cash = account($this->company, '1120');
    $capital = account($this->company, '3100');

    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => $cash->id, 'debit' => 100_000_00],
        ['account_id' => $capital->id, 'credit' => 100_000_00],
    ]);

    $reversing = app(ReverseJournalEntry::class)->handle($entry, 'data entry error');

    expect($reversing->status)->toBe(JournalStatus::Posted)
        ->and($reversing->reversal_of_id)->toBe($entry->id)
        ->and($reversing->reversal_reason)->toBe('data entry error')
        ->and($entry->fresh()->status)->toBe(JournalStatus::Reversed)
        ->and($entry->fresh()->reversed_by_id)->toBe($reversing->id);

    // Net movement on each account is zero after reversal.
    $cashClosing = PeriodBalance::query()
        ->where('account_id', $cash->id)
        ->orderByDesc('period_id')->first()?->closing->minor ?? 0;
    expect($cashClosing)->toBe(0);
});

it('requires a non-empty reason', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);

    app(ReverseJournalEntry::class)->handle($entry, '   ');
})->throws(ImmutableEntryException::class);

it('refuses to reverse a non-posted entry', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);

    $reversing = app(ReverseJournalEntry::class)->handle($entry, 'first reversal');

    // The reversing entry is itself posted, but the original is now `reversed`.
    app(ReverseJournalEntry::class)->handle($entry->fresh(), 'double reversal');
})->throws(ImmutableEntryException::class);
