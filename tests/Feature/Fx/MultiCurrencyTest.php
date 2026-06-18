<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Filament\Resources\ExchangeRates\Pages\ListExchangeRates;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Models\Customer;
use App\Models\ExchangeRate;
use App\Models\Invoice;
use App\Models\TaxCode;
use App\Services\Fx\ExchangeRateService;
use App\Support\Rbac\RbacRegistry;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('resolves the latest rate on or before a date', function () {
    $company = makeCompany();
    ExchangeRate::factory()->create(['company_id' => $company->id, 'currency_code' => 'USD', 'rate_date' => '2026-05-01', 'rate' => 55]);
    ExchangeRate::factory()->create(['company_id' => $company->id, 'currency_code' => 'USD', 'rate_date' => '2026-06-01', 'rate' => 56]);

    $svc = app(ExchangeRateService::class);

    expect($svc->rateFor($company->id, 'USD', '2026-06-15'))->toBe(56.0)
        ->and($svc->rateFor($company->id, 'USD', '2026-05-15'))->toBe(55.0)
        ->and($svc->rateFor($company->id, 'PHP', '2026-06-15'))->toBe(1.0)
        ->and($svc->toFunctional(100_000, 56.0))->toBe(5_600_000);
});

it('throws when no rate exists for a foreign currency', function () {
    $company = makeCompany();

    expect(fn () => app(ExchangeRateService::class)->rateFor($company->id, 'USD', '2026-06-15'))
        ->toThrow(RuntimeException::class);
});

it('issues a foreign-currency invoice converted to functional, with the foreign face recorded', function () {
    $company = makeCompany();
    $owner = makeUserWithRole($company, CompanyRole::Owner);
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $exempt = TaxCode::query()->where('company_id', $company->id)->where('code', 'EXEMPT')->value('id');

    $this->actingAs($owner);
    Filament::setTenant($company);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'invoice_date' => '2026-06-15',
            'pricing_mode' => 'vat_exclusive',
            'currency_code' => 'USD',
            'exchange_rate' => 56,
            'lines' => [[
                'description' => 'Export sale',
                'qty' => '1',
                'unit_price' => '1000',
                'tax_code_id' => $exempt,
                'income_account_id' => account($company, '4100')->id,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $invoice = Invoice::query()->withoutGlobalScopes()->where('company_id', $company->id)->first();

    expect($invoice->total->minor)->toBe(56_000_00)          // GL is functional PHP
        ->and($invoice->currency_code)->toBe('USD')
        ->and((int) $invoice->foreign_total)->toBe(1_000_00)  // $1,000.00 face
        ->and((float) $invoice->exchange_rate)->toBe(56.0)
        ->and($invoice->isForeignCurrency())->toBeTrue();
});

it('gates exchange rates to account.manage roles', function () {
    $company = makeCompany();
    $accountant = makeUserWithRole($company, CompanyRole::Accountant);
    $viewer = makeUserWithRole($company, CompanyRole::Viewer);

    expect($accountant->hasCompanyPermission($company->id, RbacRegistry::ACCOUNT_MANAGE))->toBeTrue()
        ->and($viewer->hasCompanyPermission($company->id, RbacRegistry::ACCOUNT_MANAGE))->toBeFalse();
});

it('renders the exchange rates list page', function () {
    $company = makeCompany();
    $this->actingAs(makeUserWithRole($company, CompanyRole::Owner));
    Filament::setTenant($company);

    Livewire::test(ListExchangeRates::class)->assertOk();
});
