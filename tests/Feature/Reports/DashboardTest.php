<?php

declare(strict_types=1);

use App\Enums\AccountSubtype;
use App\Enums\CompanyRole;
use App\Filament\Widgets\CashPosition;
use App\Filament\Widgets\FilingCalendarWidget;
use App\Filament\Widgets\FinancialSummary;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\ProfitAndLossTrend;
use App\Filament\Widgets\ReceivablesAndPayables;
use App\Filament\Widgets\RecentJournalEntries;
use App\Services\Reports\DashboardMetrics;
use App\Services\Reports\ProfitAndLossReport;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoCompanySeeder;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Livewire\Livewire;

beforeEach(function () {
    // The golden-master fixture: June 2026 trading for Dari Ventures Corp.
    $this->company = app(DemoCompanySeeder::class)->build();
    $this->travelTo(CarbonImmutable::parse('2026-06-30 09:00'));
});

it('builds monthly profit and loss that ties to the P&L report', function () {
    $series = app(DashboardMetrics::class)->monthlyProfitAndLoss($this->company->id, CarbonImmutable::parse('2026-06-30'));
    $june = end($series);
    $report = app(ProfitAndLossReport::class)->build($this->company->id, '2026-06-01', '2026-06-30');

    expect($series)->toHaveCount(12)
        ->and($series[0]['month']->format('Y-m'))->toBe('2025-07')
        ->and($series[0]['income'])->toBe(0)
        ->and($june['income'])->toBe($report['total_income'])
        ->and($june['expenses'])->toBe($report['total_expense'])
        ->and($june['net'])->toBe($report['net_income']);
});

it('tracks month-end balances that end at the balance as of today', function () {
    $metrics = app(DashboardMetrics::class);
    $cash = $metrics->monthlyBalances($this->company->id, [AccountSubtype::Cash, AccountSubtype::Bank], CarbonImmutable::parse('2026-06-30'));

    expect($cash[10]['balance'])->toBe(0) // May 2026, before any activity
        ->and(end($cash)['balance'])->toBe($metrics->balanceOf($this->company->id, [AccountSubtype::Cash, AccountSubtype::Bank], '2026-06-30'))
        ->and(end($cash)['balance'])->toBeGreaterThan(0);
});

it('renders every dashboard widget for an owner', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    $this->get(Dashboard::getUrl())->assertOk();

    Livewire::test(FinancialSummary::class)->assertOk()->assertSee('Year to date')->assertSee('Position today')->assertSee('Net income');
    Livewire::test(ProfitAndLossTrend::class)->assertOk()->assertSee('Income and expenses');
    Livewire::test(CashPosition::class)->assertOk()->assertSee('Cash position');
    Livewire::test(ReceivablesAndPayables::class)->assertOk()->assertSee('Owed to you')->assertSee('You owe');
    Livewire::test(FilingCalendarWidget::class)->assertOk()->assertSee('2550Q')->assertSee('Q2 2026');
    Livewire::test(NeedsAttention::class)->assertOk();
    Livewire::test(RecentJournalEntries::class)->assertOk()->assertSee('Recently posted');
});

it('shows a viewer the figures but not the ledger or to-dos they cannot act on', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Viewer));
    Filament::setTenant($this->company);

    expect(FinancialSummary::canView())->toBeTrue()
        ->and(RecentJournalEntries::canView())->toBeFalse();

    Livewire::test(NeedsAttention::class)
        ->assertDontSee('Journal entries to approve')
        ->assertDontSee('POS Z-readings to import')
        ->assertDontSee('Past months still open');
});
