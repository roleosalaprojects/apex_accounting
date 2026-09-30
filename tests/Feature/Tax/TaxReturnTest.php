<?php

declare(strict_types=1);

use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Enums\CompanyRole;
use App\Enums\TaxReturnType;
use App\Filament\Resources\TaxReturns\Pages\CreateTaxReturn;
use App\Filament\Resources\TaxReturns\Pages\ListTaxReturns;
use App\Models\Company;
use App\Models\TaxCode;
use App\Models\TaxReturn;
use App\Models\Vendor;
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

it('snapshots 1601-EQ figures from the EWT summary, net of the 0619-E remittances', function () {
    $company = (new DemoCompanySeeder)->build();
    payRentInMay($company);

    $figures = app(TaxReturnService::class)->compute($company, TaxReturnType::Ewt1601EQ, 2026, 2);
    $expected = app(EwtSummaryReport::class)->build($company->id, '2026-04-01', '2026-06-30');

    expect($figures)->toMatchArray($expected)
        ->and($figures['total_ewt'])->toBe(5_000_00)
        ->and($figures['remitted_0619e'])->toBe(2_500_00)   // May's 0619-E
        ->and($figures['tax_due'])->toBe(2_500_00)          // June's rent, due with the quarterly return
        ->and(TaxReturnType::Ewt1601EQ->headlineKey())->toBe('tax_due');
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

/** A second rent payment, in May: its EWT belongs on May's 0619-E; June's stays on the 1601-EQ. */
function payRentInMay(Company $company): void
{
    $landlord = Vendor::query()->withoutGlobalScopes()->where('company_id', $company->id)->whereNotNull('default_withholding_code_id')->firstOrFail();
    $vat12 = TaxCode::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('code', 'VAT12')->value('id');
    $bill = app(PostBill::class)->handle(BillData::from([
        'company_id' => $company->id, 'vendor_id' => $landlord->id, 'bill_date' => '2026-05-05', 'pricing_mode' => 'vat_inclusive',
        'lines' => [['description' => 'May rent', 'qty' => '1', 'unit_price' => 56_000_00, 'tax_code_id' => $vat12,
            'vat_bucket' => 'common', 'expense_or_asset_account_id' => account($company, '6100')->id]],
    ]));
    app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $company->id, 'vendor_id' => $landlord->id, 'payment_date' => '2026-05-20',
        'paid_from_account_id' => account($company, '1120')->id,
        'applications' => [['bill_id' => $bill->id, 'amount' => 56_000_00]],
    ]));
}

it('prepares a monthly 0619-E for the first two months of a quarter', function () {
    $company = (new DemoCompanySeeder)->build();
    payRentInMay($company);
    $service = app(TaxReturnService::class);

    $may = $service->prepare($company, TaxReturnType::Ewt0619E, 2026, null, null, month: 5);

    expect($may->period_start->toDateString())->toBe('2026-05-01')
        ->and($may->period_end->toDateString())->toBe('2026-05-31')
        ->and($may->quarter)->toBeNull()
        ->and($may->figures['total_ewt'])->toBe(2_500_00)
        ->and($may->headlineAmount())->toBe(2_500_00);

    // June is the quarter's third month: that EWT goes on the 1601-EQ instead.
    expect(fn () => $service->prepare($company, TaxReturnType::Ewt0619E, 2026, null, null, month: 6))
        ->toThrow(InvalidArgumentException::class, '1601-EQ');
});

it('prepares the annual 1604-E with the year\'s EWT by ATC, and exports its alphalist', function () {
    $company = (new DemoCompanySeeder)->build();
    payRentInMay($company);

    $annual = app(TaxReturnService::class)->prepare($company, TaxReturnType::Ewt1604E, 2026, null, null);

    expect($annual->period_start->toDateString())->toBe('2026-01-01')
        ->and($annual->period_end->toDateString())->toBe('2026-12-31')
        ->and($annual->figures['total_ewt'])->toBe(5_000_00)
        ->and($annual->figures['payees'])->toBe(1)
        ->and($annual->figures['by_atc'])->toBe([['atc' => 'WC100', 'rate_bp' => 500, 'base' => 100_000_00, 'ewt' => 5_000_00]])
        ->and($annual->headlineAmount())->toBe(5_000_00);

    $dat = app(AlphalistExporter::class)->ewt($company, '2026-01-01', '2026-12-31', '1604E');
    expect($dat)->toContain('H|1604E|')->toContain('|WC100|5.00|100000.00|5000.00');
});

it('prepares a 0619-E from the returns screen, choosing the month', function () {
    $company = (new DemoCompanySeeder)->build();
    payRentInMay($company);
    $this->actingAs(makeUserWithRole($company, CompanyRole::Owner));
    Filament::setTenant($company);

    Livewire::test(CreateTaxReturn::class)
        ->fillForm(['type' => TaxReturnType::Ewt0619E->value, 'fiscal_year' => 2026])
        ->assertFormFieldIsVisible('month')
        ->assertFormFieldIsHidden('quarter')
        ->fillForm(['month' => 5])
        ->call('create')
        ->assertHasNoFormErrors();

    $return = TaxReturn::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('type', '0619E')->sole();
    expect($return->figures['total_ewt'])->toBe(2_500_00)->and($return->period_end->toDateString())->toBe('2026-05-31');

    Livewire::test(ListTaxReturns::class)->assertSee('0619-E')->assertSee('May 1 – May 31, 2026');
});
