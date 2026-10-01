<?php

declare(strict_types=1);

use App\Actions\Ledger\CloseFiscalYear;
use App\Actions\Ledger\OpenFiscalYear;
use App\Enums\AccountSubtype;
use App\Enums\CompanyRole;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\Reports\BalanceSheetReport;
use App\Services\Reports\CashFlowReport;
use App\Services\Reports\DashboardMetrics;
use App\Services\Reports\ProfitAndLossReport;
use App\Services\Reports\StatementOfChangesInEquity;
use Carbon\CarbonImmutable;

/*
 * Earnings of earlier fiscal years belong in equity whether or not the year
 * has been closed into Retained Earnings yet; the balance sheet must balance
 * either way, and the equity statement must agree with it.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    app(OpenFiscalYear::class)->handle($this->company, 2025);

    // ₱100,000 earned in 2025, ₱50,000 so far in 2026, all still in cash.
    postEntry($this->company, '2025-06-15', [
        ['account_id' => account($this->company, '1110')->id, 'debit' => 100_000_00],
        ['account_id' => account($this->company, '4200')->id, 'credit' => 100_000_00],
    ]);
    postEntry($this->company, '2026-03-15', [
        ['account_id' => account($this->company, '1110')->id, 'debit' => 50_000_00],
        ['account_id' => account($this->company, '4200')->id, 'credit' => 50_000_00],
    ]);
});

it('balances when an earlier year has not been closed', function () {
    $bs = app(BalanceSheetReport::class)->build($this->company, '2026-06-30');

    expect($bs['balanced'])->toBeTrue()
        ->and($bs['total_assets'])->toBe(150_000_00)
        ->and($bs['current_year_earnings'])->toBe(50_000_00)
        ->and($bs['prior_years_earnings'])->toBe(100_000_00)
        ->and($bs['total_equity'])->toBe(150_000_00)
        ->and(collect($bs['equity'])->pluck('name')->all())->toContain('Retained earnings — prior years not yet closed');
});

it('shows closed years in Retained Earnings instead', function () {
    app(CloseFiscalYear::class)->handle($this->company, 2025, $this->owner);

    $bs = app(BalanceSheetReport::class)->build($this->company, '2026-06-30');
    $retained = Account::query()->withoutGlobalScopes()->where('company_id', $this->company->id)
        ->where('subtype', AccountSubtype::RetainedEarnings->value)->firstOrFail();

    expect($bs['balanced'])->toBeTrue()
        ->and($bs['prior_years_earnings'])->toBe(0)
        ->and(collect($bs['equity'])->firstWhere('account_id', $retained->id)['amount'])->toBe(100_000_00)
        ->and(collect($bs['equity'])->pluck('name')->all())->not->toContain('Retained earnings — prior years not yet closed')
        ->and($bs['total_equity'])->toBe(150_000_00);
});

it('carries earnings in the equity statement so it agrees with the balance sheet', function () {
    $equity = app(StatementOfChangesInEquity::class)->build($this->company->id, '2026-01-01', '2026-06-30');
    $bs = app(BalanceSheetReport::class)->build($this->company, '2026-06-30');

    expect($equity['opening_total'])->toBe(100_000_00)
        ->and($equity['movement_total'])->toBe(50_000_00)
        ->and($equity['closing_total'])->toBe($bs['total_equity'])
        ->and(collect($equity['rows'])->firstWhere('name', 'Earnings not yet closed to Retained Earnings'))
        ->toMatchArray(['opening' => 100_000_00, 'movement' => 50_000_00, 'closing' => 150_000_00]);
});

it('keeps the closed year\'s income statement intact: closing entries are not trading', function () {
    app(CloseFiscalYear::class)->handle($this->company, 2025, $this->owner);

    $pl = app(ProfitAndLossReport::class)->build($this->company->id, '2025-01-01', '2025-12-31');
    expect($pl['total_income'])->toBe(100_000_00)
        ->and($pl['total_expense'])->toBe(0)
        ->and($pl['net_income'])->toBe(100_000_00);

    $december = app(ProfitAndLossReport::class)->build($this->company->id, '2025-12-01', '2025-12-31');
    expect($december['net_income'])->toBe(0);

    $series = collect(app(DashboardMetrics::class)->monthlyProfitAndLoss($this->company->id, CarbonImmutable::parse('2026-03-31'), 6))
        ->keyBy(fn (array $point): string => $point['month']->format('Y-m'));
    expect($series['2025-12']['income'])->toBe(0)
        ->and($series['2025-12']['expenses'])->toBe(0)
        ->and($series['2026-03']['income'])->toBe(50_000_00);

    $cash = app(CashFlowReport::class)->build($this->company->id, '2025-01-01', '2025-12-31');
    expect($cash['balanced'])->toBeTrue()->and($cash['cash_change'])->toBe(100_000_00);

    // The ledger itself still shows the closing entry, flagged as such.
    expect(JournalEntry::query()->where('is_closing', true)->count())->toBe(1);
});
