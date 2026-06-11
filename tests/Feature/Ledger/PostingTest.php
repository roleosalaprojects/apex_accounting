<?php

declare(strict_types=1);

use App\Enums\JournalStatus;
use App\Exceptions\Ledger\ClosedPeriodException;
use App\Exceptions\Ledger\ImmutableEntryException;
use App\Exceptions\Ledger\MissingPartnerException;
use App\Exceptions\Ledger\UnbalancedEntryException;
use App\Models\AccountingPeriod;
use App\Models\Department;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->company = makeCompany();
});

it('posts a balanced entry and assigns a gapless number', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100_000_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100_000_00],
    ]);

    expect($entry->status)->toBe(JournalStatus::Posted)
        ->and($entry->number)->toBe('JE-2026-000001')
        ->and($entry->total_debits->minor)->toBe(100_000_00)
        ->and($entry->total_credits->minor)->toBe(100_000_00)
        ->and($entry->lines)->toHaveCount(2);
});

it('rejects an unbalanced entry', function () {
    postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100_000_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 90_000_00],
    ]);
})->throws(UnbalancedEntryException::class);

it('rejects a line with both debit and credit', function () {
    postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100, 'credit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);
})->throws(UnbalancedEntryException::class);

it('rejects posting into a closed period', function () {
    AccountingPeriod::query()->where('company_id', $this->company->id)
        ->where('period_no', 6)->update(['status' => 'closed']);

    postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);
})->throws(ClosedPeriodException::class);

it('rejects posting where no period exists for the date', function () {
    postEntry($this->company, '2030-01-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);
})->throws(ClosedPeriodException::class);

it('forbids mutating a posted entry', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);

    $entry->memo = 'tampered';
    $entry->save();
})->throws(ImmutableEntryException::class);

it('forbids mutating the lines of a posted entry', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);

    $line = $entry->lines()->first();
    $line->debit = 999;
    $line->save();
})->throws(ImmutableEntryException::class);

it('forbids deleting a posted entry', function () {
    $entry = postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
    ]);

    $entry->delete();
})->throws(ImmutableEntryException::class);

it('requires a partner on AR/AP control account lines', function () {
    postEntry($this->company, '2026-06-15', [
        ['account_id' => account($this->company, '1200')->id, 'debit' => 100], // AR control, no partner
        ['account_id' => account($this->company, '4200')->id, 'credit' => 100],
    ]);
})->throws(MissingPartnerException::class);

it('accepts AR control lines that carry a partner', function () {
    // A real Customer arrives in Phase 3; the control-account guard only requires
    // *some* partner morph, so any model stands in here.
    $partner = User::factory()->create();

    $entry = postEntry($this->company, '2026-06-15', [
        [
            'account_id' => account($this->company, '1200')->id,
            'debit' => 100,
            'partner_type' => $partner->getMorphClass(),
            'partner_id' => $partner->id,
        ],
        ['account_id' => account($this->company, '4200')->id, 'credit' => 100],
    ]);

    expect($entry->lines->first()->partner_id)->toBe($partner->id);
});

it('persists accounting dimensions through to journal lines', function () {
    $dept = Department::factory()->create(['company_id' => $this->company->id]);
    $project = Project::factory()->create(['company_id' => $this->company->id]);

    $entry = postEntry($this->company, '2026-06-15', [
        [
            'account_id' => account($this->company, '6100')->id,
            'debit' => 50_000_00,
            'department_id' => $dept->id,
            'project_id' => $project->id,
        ],
        ['account_id' => account($this->company, '1120')->id, 'credit' => 50_000_00],
    ]);

    $line = $entry->lines->firstWhere('account_id', account($this->company, '6100')->id);
    expect($line->department_id)->toBe($dept->id)
        ->and($line->project_id)->toBe($project->id);

    // And they are queryable on journal_lines (GL drill-down by dimension).
    $count = JournalLine::query()->where('department_id', $dept->id)->count();
    expect($count)->toBe(1);
});

it('assigns sequential, unique numbers across many posts', function () {
    $numbers = [];
    for ($i = 0; $i < 25; $i++) {
        $numbers[] = postEntry($this->company, '2026-06-15', [
            ['account_id' => account($this->company, '1120')->id, 'debit' => 100],
            ['account_id' => account($this->company, '3100')->id, 'credit' => 100],
        ])->number;
    }

    expect($numbers)->toHaveCount(25)
        ->and(array_unique($numbers))->toHaveCount(25)
        ->and($numbers[0])->toBe('JE-2026-000001')
        ->and($numbers[24])->toBe('JE-2026-000025')
        ->and(JournalEntry::query()->count())->toBe(25);
});
