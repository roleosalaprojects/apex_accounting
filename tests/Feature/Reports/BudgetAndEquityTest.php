<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\BudgetVsActual;
use App\Filament\Pages\Reports\ChangesInEquity;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Services\Reports\BudgetVsActualReport;
use App\Services\Reports\StatementOfChangesInEquity;
use App\Support\Rbac\RbacRegistry;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

it('compares each budget line to the ledger actual', function () {
    $sales = account($this->company, '4100'); // income, credit-normal

    postEntry($this->company, '2026-03-01', [
        ['account_id' => account($this->company, '1110')->id, 'debit' => 100_000_00],
        ['account_id' => $sales->id, 'credit' => 100_000_00],
    ], $this->owner);

    $budget = Budget::factory()->create(['company_id' => $this->company->id, 'fiscal_year' => 2026, 'name' => 'Ops Plan']);
    BudgetLine::factory()->create(['budget_id' => $budget->id, 'account_id' => $sales->id, 'amount' => 120_000_00]);

    $r = app(BudgetVsActualReport::class)->build($this->company->id, $budget->id, '2026-01-01', '2026-12-31');

    expect($r['total_budget'])->toBe(120_000_00)
        ->and($r['total_actual'])->toBe(100_000_00)
        ->and($r['total_variance'])->toBe(-20_000_00)
        ->and($r['rows'][0]['pct'])->toBe(83.3);
});

it('reports beginning, change and ending equity that ties out', function () {
    $equity = Account::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)
        ->where('type', AccountType::Equity->value)
        ->orderBy('code')->firstOrFail();

    postEntry($this->company, '2026-02-01', [
        ['account_id' => account($this->company, '1110')->id, 'debit' => 500_000_00],
        ['account_id' => $equity->id, 'credit' => 500_000_00],
    ], $this->owner);

    $r = app(StatementOfChangesInEquity::class)->build($this->company->id, '2026-01-01', '2026-12-31');

    // Equity is credit-normal: a credit increases it by a positive movement.
    expect($r['movement_total'])->toBe(500_000_00)
        ->and($r['closing_total'])->toBe(500_000_00)
        ->and($r['opening_total'])->toBe(0);
});

it('gates budget management to owners/accountants, not viewers', function () {
    $accountant = makeUserWithRole($this->company, CompanyRole::Accountant);
    $viewer = makeUserWithRole($this->company, CompanyRole::Viewer);

    expect($this->owner->hasCompanyPermission($this->company->id, RbacRegistry::BUDGET_MANAGE))->toBeTrue()
        ->and($accountant->hasCompanyPermission($this->company->id, RbacRegistry::BUDGET_MANAGE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($this->company->id, RbacRegistry::BUDGET_MANAGE))->toBeFalse();
});

it('renders the new report pages and the budget resource', function () {
    $budget = Budget::factory()->create(['company_id' => $this->company->id, 'fiscal_year' => 2026, 'name' => 'Ops Plan']);

    Livewire::test(ChangesInEquity::class)
        ->set('from', '2026-01-01')->set('asOf', '2026-06-30')
        ->assertOk();

    Livewire::test(BudgetVsActual::class)
        ->set('entity', (string) $budget->id)
        ->set('from', '2026-01-01')->set('asOf', '2026-12-31')
        ->assertOk();

    Livewire::test(ListBudgets::class)->assertOk();
});
