<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Filament\Resources\TaxCodes\Pages\EditTaxCode;
use App\Filament\Resources\TaxCodes\Pages\ListTaxCodes;
use App\Filament\Resources\TaxCodes\TaxCodeResource;
use App\Filament\Resources\WithholdingCodes\Pages\CreateWithholdingCode;
use App\Filament\Resources\WithholdingCodes\Pages\EditWithholdingCode;
use App\Filament\Resources\WithholdingCodes\Pages\ListWithholdingCodes;
use App\Filament\Resources\WithholdingCodes\WithholdingCodeResource;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\WithholdingCode;
use App\Models\WithholdingTransaction;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

it('lists the VAT codes with their rates and lets the owner rename one', function () {
    $vat = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->sole();

    Livewire::test(ListTaxCodes::class)
        ->assertCanSeeTableRecords(TaxCode::query()->where('company_id', $this->company->id)->get())
        ->assertSee('12.00%');

    Livewire::test(EditTaxCode::class, ['record' => $vat->getRouteKey()])
        ->assertFormSet(['code' => 'VAT12', 'rate_bp' => '12.00'])
        ->fillForm(['name' => 'Output VAT 12%', 'rate_bp' => '12'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vat->refresh()->name)->toBe('Output VAT 12%')
        ->and($vat->rate_bp)->toBe(1200)
        ->and(TaxCodeResource::canCreate())->toBeFalse()
        ->and(TaxCodeResource::canDelete($vat))->toBeFalse();
});

it('adds, edits and removes EWT codes, keeping the rate in basis points', function () {
    Livewire::test(CreateWithholdingCode::class)
        ->fillForm(['code' => 'WC010', 'name' => 'EWT — Professional 15%', 'atc' => 'WI011', 'rate_bp' => '15', 'applies_to' => 'purchase'])
        ->call('create')
        ->assertHasNoFormErrors();

    $code = WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC010')->sole();
    expect($code->rate_bp)->toBe(1500)->and($code->atc)->toBe('WI011');

    Livewire::test(EditWithholdingCode::class, ['record' => $code->getRouteKey()])
        ->assertFormSet(['rate_bp' => '15.00'])
        ->fillForm(['rate_bp' => '10.5'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($code->refresh()->rate_bp)->toBe(1050);

    Livewire::test(ListWithholdingCodes::class)
        ->assertSee('10.50%')
        ->callTableAction(DeleteAction::class, $code);
    expect(WithholdingCode::query()->whereKey($code->id)->exists())->toBeFalse();
});

it('refuses a duplicate code and keeps codes that vendors or payments use', function () {
    $rental = WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC100')->sole();

    Livewire::test(CreateWithholdingCode::class)
        ->fillForm(['code' => 'WC100', 'name' => 'Duplicate', 'atc' => 'WC100', 'rate_bp' => '5', 'applies_to' => 'purchase'])
        ->call('create')
        ->assertHasFormErrors(['code']);

    Vendor::factory()->create(['company_id' => $this->company->id, 'default_withholding_code_id' => $rental->id]);
    expect(WithholdingCodeResource::canDelete($rental))->toBeFalse();

    $services = WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC160')->sole();
    WithholdingTransaction::query()->create([
        'company_id' => $this->company->id,
        'vendor_id' => Vendor::factory()->create(['company_id' => $this->company->id])->id,
        'withholding_code_id' => $services->id,
        'atc' => $services->atc,
        'base' => 10_000_00,
        'rate_bp' => $services->rate_bp,
        'ewt' => 200_00,
        'transaction_date' => '2026-05-20',
    ]);
    expect(WithholdingCodeResource::canDelete($services))->toBeFalse()
        ->and(WithholdingCodeResource::canDelete(WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC158')->sole()))->toBeTrue();
});

it('keeps the tax settings out of reach of everyone but company managers', function () {
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Accountant));

    expect(TaxCodeResource::canViewAny())->toBeFalse()
        ->and(WithholdingCodeResource::canViewAny())->toBeFalse()
        ->and(WithholdingCodeResource::canCreate())->toBeFalse();
});
