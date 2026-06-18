<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\ComparativeBalanceSheet;
use App\Filament\Pages\Reports\ComparativeProfitAndLoss;
use Database\Seeders\DemoCompanySeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = (new DemoCompanySeeder)->build();
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);
});

it('shows current vs prior year on the comparative P&L', function () {
    Livewire::test(ComparativeProfitAndLoss::class)
        ->set('from', '2026-01-01')->set('asOf', '2026-06-30')
        ->assertOk()
        ->assertSee('184,600.00'); // current-year net income (§20); prior year is 0
});

it('shows current vs prior year on the comparative balance sheet', function () {
    Livewire::test(ComparativeBalanceSheet::class)
        ->set('asOf', '2026-06-30')
        ->assertOk()
        ->assertSee('3,423,100.00'); // current total assets (§20)
});
