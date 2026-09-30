<?php

declare(strict_types=1);

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\CompanyRole;
use App\Enums\ItemType;
use App\Enums\NormalBalance;
use App\Filament\Imports\AccountImporter;
use App\Filament\Imports\CompanyImporter;
use App\Filament\Imports\CustomerImporter;
use App\Filament\Imports\ItemImporter;
use App\Filament\Imports\VendorImporter;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Vendor;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
 * Onboarding by CSV: customers, vendors, items and accounts are imported
 * with column mapping, matched on their code so a re-import updates, and a
 * bad row fails with a reason instead of stopping the file.
 */
beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
});

/** Run one CSV row through an importer the way the import job does. */
function importRow(string $importer, array $row, array $map): void
{
    $import = Import::query()->create([
        'file_name' => 'test.csv', 'file_path' => 'test.csv', 'importer' => $importer,
        'total_rows' => 1, 'user_id' => test()->owner->id,
    ]);
    (new $importer($import, $map, CompanyImporter::optionsFor()))($row);
}

it('imports customers, matching on code so a second import updates', function () {
    $map = ['code' => 'Customer Code', 'name' => 'Name', 'tin' => 'TIN', 'email' => 'Email', 'terms_days' => 'Terms', 'credit_limit' => 'Credit Limit', 'is_withholding_agent' => 'EWT Agent'];

    importRow(CustomerImporter::class, ['Customer Code' => 'GHG', 'Name' => 'Golden Harvest Grocery', 'TIN' => '123-456-789-00000', 'Email' => 'ap@ghg.test', 'Terms' => '15', 'Credit Limit' => '150,000.00', 'EWT Agent' => 'yes'], $map);
    $customer = Customer::query()->where('code', 'GHG')->sole();
    expect($customer->name)->toBe('Golden Harvest Grocery')
        ->and($customer->company_id)->toBe($this->company->id)
        ->and($customer->terms_days)->toBe(15)
        ->and($customer->credit_limit->minor)->toBe(150_000_00)
        ->and($customer->is_withholding_agent)->toBeTrue()
        ->and($customer->email)->toBe('ap@ghg.test');

    importRow(CustomerImporter::class, ['Customer Code' => 'GHG', 'Name' => 'Golden Harvest Grocery Inc.', 'TIN' => '', 'Email' => '', 'Terms' => '30', 'Credit Limit' => '', 'EWT Agent' => 'no'], $map);
    expect(Customer::query()->where('code', 'GHG')->count())->toBe(1)
        ->and($customer->refresh()->name)->toBe('Golden Harvest Grocery Inc.')
        ->and($customer->terms_days)->toBe(30)
        ->and($customer->is_withholding_agent)->toBeFalse();

    expect(fn () => importRow(CustomerImporter::class, ['Customer Code' => 'BAD', 'Name' => 'No mail', 'TIN' => '', 'Email' => 'not-an-email', 'Terms' => '', 'Credit Limit' => '', 'EWT Agent' => ''], $map))
        ->toThrow(ValidationException::class);
});

it('imports vendors with their default EWT code, and refuses an unknown one', function () {
    $map = ['code' => 'Code', 'name' => 'Vendor', 'is_vat_registered' => 'VAT', 'default_withholding_code' => 'EWT'];

    importRow(VendorImporter::class, ['Code' => 'RTI', 'Vendor' => 'Rice Trader Inc.', 'VAT' => 'yes', 'EWT' => 'WC158'], $map);
    $vendor = Vendor::query()->where('code', 'RTI')->sole();
    expect($vendor->is_vat_registered)->toBeTrue()
        ->and($vendor->defaultWithholdingCode?->code)->toBe('WC158');

    expect(fn () => importRow(VendorImporter::class, ['Code' => 'XX', 'Vendor' => 'Unknown code', 'VAT' => 'no', 'EWT' => 'WC999'], $map))
        ->toThrow(RowImportFailedException::class, 'WC999');
});

it('imports items with accounts given by code', function () {
    $map = ['sku' => 'SKU', 'name' => 'Item', 'type' => 'Type', 'unit' => 'Unit', 'is_vat_exempt_item' => 'Exempt', 'default_sales_price' => 'Price', 'default_purchase_price' => 'Cost', 'income_account' => 'Income', 'cogs_account' => 'COGS', 'inventory_account' => 'Inventory'];

    importRow(ItemImporter::class, ['SKU' => 'RICE-50', 'Item' => 'Rice 50kg', 'Type' => 'Inventory', 'Unit' => 'sack', 'Exempt' => 'yes', 'Price' => '2,900', 'Cost' => '2,600', 'Income' => '4100', 'COGS' => '5100', 'Inventory' => '1300'], $map);
    $item = Item::query()->where('sku', 'RICE-50')->sole();
    expect($item->type)->toBe(ItemType::Inventory)
        ->and($item->is_vat_exempt_item)->toBeTrue()
        ->and($item->default_sales_price->minor)->toBe(2_900_00)
        ->and($item->income_account_id)->toBe(account($this->company, '4100')->id)
        ->and($item->inventory_account_id)->toBe(account($this->company, '1300')->id);

    importRow(ItemImporter::class, ['SKU' => 'SVC-DEL', 'Item' => 'Delivery service', 'Type' => 'service', 'Unit' => 'trip', 'Exempt' => 'no', 'Price' => '500', 'Cost' => '', 'Income' => '4200', 'COGS' => '', 'Inventory' => ''], $map);
    expect(Item::query()->where('sku', 'SVC-DEL')->sole()->type)->toBe(ItemType::Service);

    expect(fn () => importRow(ItemImporter::class, ['SKU' => 'X', 'Item' => 'Bad account', 'Type' => 'service', 'Unit' => '', 'Exempt' => '', 'Price' => '', 'Cost' => '', 'Income' => '9999', 'COGS' => '', 'Inventory' => ''], $map))
        ->toThrow(RowImportFailedException::class, "no account with code '9999'");
});

it('imports accounts, deriving type and normal balance from the subtype', function () {
    $map = ['code' => 'Code', 'name' => 'Account', 'subtype' => 'Type'];

    importRow(AccountImporter::class, ['Code' => '6150', 'Account' => 'Delivery expense', 'Type' => 'Expense'], $map);
    importRow(AccountImporter::class, ['Code' => '1520', 'Account' => 'Accum. depreciation — vehicles', 'Type' => 'accumulated depreciation'], $map);

    $expense = Account::query()->where('code', '6150')->sole();
    $accum = Account::query()->where('code', '1520')->sole();
    expect($expense->subtype)->toBe(AccountSubtype::Expense)
        ->and($expense->type)->toBe(AccountType::Expense)
        ->and($expense->normal_balance)->toBe(NormalBalance::Debit)
        ->and($expense->is_system)->toBeFalse()
        ->and($accum->type)->toBe(AccountType::Asset)
        ->and($accum->normal_balance)->toBe(NormalBalance::Credit);

    expect(fn () => importRow(AccountImporter::class, ['Code' => '7000', 'Account' => 'Mystery', 'Type' => 'mystery'], $map))
        ->toThrow(ValidationException::class);
});

it('offers the import on each master-data list', function () {
    Livewire::test(ListCustomers::class)->assertActionExists('import');
    Livewire::test(ListVendors::class)->assertActionExists('import');
    Livewire::test(ListItems::class)->assertActionExists('import');
    Livewire::test(ListAccounts::class)->assertActionExists('import');
});
