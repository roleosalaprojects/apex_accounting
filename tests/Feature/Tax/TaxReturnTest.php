<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Enums\TaxReturnType;
use App\Filament\Resources\TaxReturns\Pages\ListTaxReturns;
use App\Services\Reports\EwtSummaryReport;
use App\Services\Reports\VatSummaryReport;
use App\Services\Tax\AlphalistExporter;
use App\Services\Tax\SlspDatExporter;
use App\Services\Tax\TaxReturnService;
use App\Support\Rbac\RbacRegistry;
use Database\Seeders\DemoCompanySeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('computes fiscal quarter ranges from the company fiscal-year start', function () {
    $company = makeCompany(['fiscal_year_start_month' => 1]);

    expect(app(TaxReturnService::class)->quarterRange($company, 2026, 2))
        ->toBe(['from' => '2026-04-01', 'to' => '2026-06-30']);
});

it('snapshots 2550Q figures from the VAT summary', function () {
    $company = (new DemoCompanySeeder)->build();

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::Vat2550Q, 2026, 2);
    $expected = app(VatSummaryReport::class)->build($company->id, 2026, 2, '2026-04-01', '2026-06-30');

    expect($figures)->toBe($expected)
        ->and($figures['carryover'])->toBe(12_600_00); // §20 golden master
});

it('snapshots 1601-EQ figures from the EWT summary', function () {
    $company = (new DemoCompanySeeder)->build();

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::Ewt1601EQ, 2026, 2);
    $expected = app(EwtSummaryReport::class)->build($company->id, '2026-04-01', '2026-06-30');

    expect($figures)->toBe($expected)
        ->and($figures['total_ewt'])->toBeGreaterThan(0);
});

it('computes 2551Q percentage tax at 3% of gross sales', function () {
    $company = (new DemoCompanySeeder)->build();

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::Pct2551Q, 2026, 2);

    expect($figures['rate'])->toBe(0.03)
        ->and($figures['gross_receipts'])->toBeGreaterThan(0)
        ->and($figures['tax_due'])->toBe((int) round($figures['gross_receipts'] * 0.03));
});

it('persists a prepared return with its headline amount', function () {
    $company = (new DemoCompanySeeder)->build();

    $return = app(TaxReturnService::class)->prepare($company, TaxReturnType::Vat2550Q, 2026, 2, null);

    expect($return->exists)->toBeTrue()
        ->and($return->status)->toBe('draft')
        ->and($return->headlineAmount())->toBe((int) $return->figures['vat_payable']);
});

it('exports an SLSP file grouped by partner', function () {
    $company = (new DemoCompanySeeder)->build();

    $dat = app(SlspDatExporter::class)->sales($company, '2026-01-01', '2026-12-31');

    expect($dat)->toContain('H|S|')   // header
        ->and($dat)->toContain('D|')  // partner detail line(s)
        ->and($dat)->toContain('C|'); // control footer
});

it('gates tax returns to owners and accountants, not viewers', function () {
    $company = makeCompany();
    $owner = makeUserWithRole($company, CompanyRole::Owner);
    $accountant = makeUserWithRole($company, CompanyRole::Accountant);
    $viewer = makeUserWithRole($company, CompanyRole::Viewer);

    expect($owner->hasCompanyPermission($company->id, RbacRegistry::TAX_RETURNS_MANAGE))->toBeTrue()
        ->and($accountant->hasCompanyPermission($company->id, RbacRegistry::TAX_RETURNS_MANAGE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($company->id, RbacRegistry::TAX_RETURNS_MANAGE))->toBeFalse();
});

it('renders the tax returns list with a prepared return', function () {
    $company = (new DemoCompanySeeder)->build();
    app(TaxReturnService::class)->prepare($company, TaxReturnType::Vat2550Q, 2026, 2, null);
    $this->actingAs(makeUserWithRole($company, CompanyRole::Owner));
    Filament::setTenant($company);

    Livewire::test(ListTaxReturns::class)->assertOk()->assertSee('2550Q');
});

it('computes 1702Q income tax at 25% of cumulative book net income', function () {
    $company = (new DemoCompanySeeder)->build();

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::IncomeTax1702Q, 2026, 2);

    expect($figures['rate'])->toBe(0.25)
        ->and($figures['net_income'])->toBe(184_600_00) // §20 golden master (Jan–Jun cumulative)
        ->and($figures['tax_due'])->toBe((int) round(184_600_00 * 0.25));
});

it('exports an EWT alphalist (QAP) grouped by payee', function () {
    $company = (new DemoCompanySeeder)->build();

    $dat = app(AlphalistExporter::class)->ewt($company, '2026-01-01', '2026-12-31');

    expect($dat)->toContain('H|QAP|')  // header
        ->and($dat)->toContain('D|')   // payee detail line(s)
        ->and($dat)->toContain('C|');  // control footer
});

it('computes 1701Q individual income tax using graduated rates', function () {
    $company = makeCompany();
    $actor = makeUserWithRole($company, CompanyRole::Accountant);

    postEntry($company, '2026-02-01', [
        ['account_id' => account($company, '1110')->id, 'debit' => 500_000_00],
        ['account_id' => account($company, '4100')->id, 'credit' => 500_000_00],
    ], $actor);

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::IncomeTax1701Q, 2026, 1);

    // ₱500,000 → ₱22,500 + 20% of (₱500,000 − ₱400,000) = ₱42,500
    expect($figures['net_income'])->toBe(500_000_00)
        ->and($figures['tax_due'])->toBe(42_500_00);
});

it('charges no individual income tax below the ₱250,000 exemption', function () {
    $company = makeCompany();
    $actor = makeUserWithRole($company, CompanyRole::Accountant);

    postEntry($company, '2026-02-01', [
        ['account_id' => account($company, '1110')->id, 'debit' => 200_000_00],
        ['account_id' => account($company, '4100')->id, 'credit' => 200_000_00],
    ], $actor);

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::IncomeTax1701Q, 2026, 1);

    expect($figures['tax_due'])->toBe(0);
});
