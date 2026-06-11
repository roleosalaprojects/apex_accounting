<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Filament\Pages\Reports\ApAging;
use App\Filament\Pages\Reports\ArAging;
use App\Filament\Pages\Reports\BalanceSheet;
use App\Filament\Pages\Reports\EwtSummary;
use App\Filament\Pages\Reports\ProfitAndLoss;
use App\Filament\Pages\Reports\PurchaseBookPage;
use App\Filament\Pages\Reports\SalesBookPage;
use App\Filament\Pages\Reports\TrialBalance;
use App\Filament\Pages\Reports\VatSummary;
use Database\Seeders\DemoCompanySeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = (new DemoCompanySeeder)->build();
    $owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($owner);
    Filament::setTenant($this->company);
});

it('renders every report page against the golden master', function (string $page) {
    Livewire::test($page)
        ->set('from', '2026-01-01')
        ->set('asOf', '2026-06-30')
        ->assertOk();
})->with([
    TrialBalance::class,
    ProfitAndLoss::class,
    BalanceSheet::class,
    ArAging::class,
    ApAging::class,
    SalesBookPage::class,
    PurchaseBookPage::class,
    VatSummary::class,
    EwtSummary::class,
]);

it('shows the §20 figures on the financial reports', function () {
    Livewire::test(ProfitAndLoss::class)
        ->set('from', '2026-01-01')->set('asOf', '2026-06-30')
        ->assertSee('184,600.00'); // net income

    Livewire::test(BalanceSheet::class)
        ->set('asOf', '2026-06-30')
        ->assertSee('3,423,100.00'); // total assets

    Livewire::test(VatSummary::class)
        ->set('from', '2026-04-01')->set('asOf', '2026-06-30')
        ->assertSee('12,600.00'); // excess input VAT carryover
});
