<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Branches\Pages\CreateBranch;
use App\Filament\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Filament\Resources\Funds\Pages\CreateFund;
use App\Filament\Resources\Funds\Pages\EditFund;
use App\Filament\Resources\Funds\Pages\ListFunds;
use App\Filament\Resources\Items\Pages\CreateItem;
use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Fund;
use App\Models\Item;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

function memberNamed(Company $company, CompanyRole $role, string $name): User
{
    $user = makeUserWithRole($company, $role);
    $user->forceFill(['name' => $name])->save();

    return $user;
}

beforeEach(function () {
    $this->other = Company::factory()->create(['name' => 'Someone Else Inc.']);
    $this->outsider = memberNamed($this->other, CompanyRole::Owner, 'Outsider Olga');

    $this->company = makeCompany();
    $this->owner = memberNamed($this->company, CompanyRole::Owner, 'Owner Olivia');
    $this->accountant = memberNamed($this->company, CompanyRole::Accountant, 'Accountant Andy');

    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

it('creates master data in the current company, with the creator picked by name', function () {
    Livewire::test(CreateFund::class)
        ->assertFormSet(['created_by' => $this->owner->id]) // defaults to the signed-in user
        ->fillForm(['code' => 'drvp-ph', 'name' => 'Project Handling', 'is_active' => true, 'created_by' => $this->accountant->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $fund = Fund::query()->where('code', 'drvp-ph')->firstOrFail();

    expect($fund->company_id)->toBe($this->company->id)
        ->and($fund->created_by)->toBe($this->accountant->id);

    Livewire::test(ListFunds::class)
        ->sortTable('createdBy.name')
        ->assertCanSeeTableRecords([$fund])
        ->assertSee('Accountant Andy');
});

it('offers only members of the current company as the creator', function () {
    Livewire::test(CreateFund::class)
        ->assertSee('Accountant Andy')
        ->assertDontSee('Outsider Olga')
        ->fillForm(['code' => 'F-2', 'name' => 'Other', 'is_active' => true, 'created_by' => $this->outsider->id])
        ->call('create')
        ->assertHasFormErrors(['created_by']);

    expect(Fund::query()->where('code', 'F-2')->exists())->toBeFalse();
});

it('never offers or accepts another company on master-data forms', function () {
    foreach ([CreateAccount::class, CreateBranch::class, CreateCustomer::class, CreateDepartment::class,
        CreateFund::class, CreateItem::class, CreateProject::class, CreateVendor::class] as $page) {
        Livewire::test($page)
            ->assertFormFieldDoesNotExist('company_id')
            ->assertDontSee('Someone Else Inc.');
    }

    $fund = Fund::query()->create(['company_id' => $this->company->id, 'code' => 'F-1', 'name' => 'General', 'is_active' => true]);

    Livewire::test(EditFund::class, ['record' => $fund->getKey()])
        ->set('data.company_id', $this->other->id)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Fund::query()->withoutGlobalScopes()->findOrFail($fund->id)->company_id)->toBe($this->company->id);
});

it('shows peso amounts in pesos on edit forms and saves them back unchanged', function () {
    $item = Item::factory()->create(['company_id' => $this->company->id, 'default_sales_price' => 1_234_56, 'default_purchase_price' => 999_00]);

    Livewire::test(EditItem::class, ['record' => $item->getKey()])
        ->assertFormSet(['default_sales_price' => '1234.56', 'default_purchase_price' => '999.00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->fresh()->default_sales_price->minor)->toBe(1_234_56)
        ->and($item->fresh()->default_purchase_price->minor)->toBe(999_00);

    Livewire::test(EditItem::class, ['record' => $item->getKey()])
        ->fillForm(['default_sales_price' => '1500.25'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->fresh()->default_sales_price->minor)->toBe(1_500_25);

    $customer = Customer::factory()->create(['company_id' => $this->company->id, 'credit_limit' => 50_000_00]);

    Livewire::test(EditCustomer::class, ['record' => $customer->getKey()])
        ->assertFormSet(['credit_limit' => '50000.00'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->fresh()->credit_limit?->minor)->toBe(50_000_00);

    Livewire::test(EditCustomer::class, ['record' => $customer->getKey()])
        ->fillForm(['credit_limit' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->fresh()->credit_limit)->toBeNull();
});
