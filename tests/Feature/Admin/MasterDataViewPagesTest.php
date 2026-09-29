<?php

declare(strict_types=1);

use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\ItemType;
use App\Filament\Pages\Reports\GeneralLedger;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Filament\Resources\BankAccounts\Pages\ViewBankAccount;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\InvoicesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\PaymentsRelationManager as CustomerPaymentsRelationManager;
use App\Filament\Resources\Items\Pages\ViewItem;
use App\Filament\Resources\Items\RelationManagers\PurchasesRelationManager;
use App\Filament\Resources\Items\RelationManagers\SalesRelationManager;
use App\Filament\Resources\Vendors\Pages\ViewVendor;
use App\Filament\Resources\Vendors\RelationManagers\BillsRelationManager;
use App\Filament\Resources\Vendors\RelationManagers\PaymentsRelationManager as VendorPaymentsRelationManager;
use App\Filament\Support\Widgets\LedgerActivity;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\JournalLine;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Services\Reports\AccountSummary;
use App\Services\Reports\CustomerSummary;
use App\Services\Reports\ItemSummary;
use App\Services\Reports\VendorSummary;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * One quarter of rice trading, as of Jun 30, 2026:
 *   May 2  bill B1   1,000 sacks @ ₱2,000   ₱2,000,000   due Jun 1 (overdue)
 *   Jun 5  invoice I1  300 sacks @ ₱2,500     ₱750,000   due Jun 20 (overdue)
 *   Jun 10 bill B2     100 sacks @ ₱2,100     ₱210,000   due Jul 10
 *   Jun 15 pay ₱500,000 on B1
 *   Jun 25 invoice I2   40 sacks @ ₱2,500     ₱100,000   due Jul 25
 *   Jun 28 receive ₱250,000 on I1
 * Weighted average: ₱2,000 until B2, then (700 × 2,000 + 100 × 2,100) / 800 = ₱2,012.50.
 * 760 sacks remain, worth ₱1,529,500; cost of sales 300 × 2,000 + 40 × 2,012.50 = ₱680,500.
 */
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-30 09:00'));

    $this->company = makeCompany();
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    $exempt = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id');
    $this->vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Isabela Rice Mill']);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Golden Harvest Grocery']);
    $this->rice = Item::factory()->create([
        'company_id' => $this->company->id, 'sku' => 'RICE-25', 'name' => 'Rice 25kg', 'type' => ItemType::Inventory,
        'is_vat_exempt_item' => true, 'unit' => 'sack_25kg',
        'income_account_id' => account($this->company, '4100')->id,
        'cogs_account_id' => account($this->company, '5100')->id,
        'inventory_account_id' => account($this->company, '1300')->id,
    ]);
    $this->bank = BankAccount::query()->create([
        'company_id' => $this->company->id, 'account_id' => account($this->company, '1120')->id,
        'bank_name' => 'BDO Unibank', 'account_no' => '0045-1234-5678', 'is_active' => true,
    ]);

    postEntry($this->company, '2026-01-02', [
        ['account_id' => account($this->company, '1120')->id, 'debit' => 1_000_000_00],
        ['account_id' => account($this->company, '3100')->id, 'credit' => 1_000_000_00],
    ]);

    $bill = fn (string $date, string $due, string $qty, int $price): Bill => app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'bill_date' => $date, 'due_date' => $due,
        'lines' => [['description' => 'Rice', 'qty' => $qty, 'unit_price' => $price, 'tax_code_id' => $exempt,
            'item_id' => $this->rice->id, 'expense_or_asset_account_id' => account($this->company, '1300')->id]],
    ]));
    $invoice = fn (string $date, string $due, string $qty): Invoice => app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => $date, 'due_date' => $due,
        'lines' => [['description' => 'Rice', 'qty' => $qty, 'unit_price' => 2_500_00, 'tax_code_id' => $exempt,
            'item_id' => $this->rice->id, 'income_account_id' => account($this->company, '4100')->id]],
    ]));

    $this->b1 = $bill('2026-05-02', '2026-06-01', '1000', 2_000_00);
    $this->i1 = $invoice('2026-06-05', '2026-06-20', '300');
    $this->b2 = $bill('2026-06-10', '2026-07-10', '100', 2_100_00);
    app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->vendor->id, 'payment_date' => '2026-06-15',
        'paid_from_account_id' => account($this->company, '1120')->id,
        'applications' => [['bill_id' => $this->b1->id, 'amount' => 500_000_00]],
    ]));
    $this->i2 = $invoice('2026-06-25', '2026-07-25', '40');
    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'payment_date' => '2026-06-28',
        'deposit_to_account_id' => account($this->company, '1120')->id, 'amount' => 250_000_00,
        'applications' => [['invoice_id' => $this->i1->id, 'amount' => 250_000_00]],
    ]));
});

it('sums what a customer owes, what is past due and what was invoiced', function () {
    expect(app(CustomerSummary::class)->build($this->customer, '2026-01-01', '2026-06-30'))->toBe([
        'balance' => 600_000_00,
        'overdue' => 500_000_00,
        'open_invoices' => 2,
        'overdue_invoices' => 1,
        'invoiced' => 850_000_00,
        'last_payment' => ['date' => '2026-06-28', 'amount' => 250_000_00],
    ]);

    // As of Jun 20 nothing was due yet, and I2 and the payment had not happened.
    expect(app(CustomerSummary::class)->build($this->customer, '2026-01-01', '2026-06-20'))->toMatchArray([
        'balance' => 750_000_00, 'overdue' => 0, 'open_invoices' => 1, 'invoiced' => 750_000_00, 'last_payment' => null,
    ]);

    // I2 settled on Jun 30 is paid today, but was still owed the day before.
    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'payment_date' => '2026-06-30',
        'deposit_to_account_id' => account($this->company, '1120')->id, 'amount' => 100_000_00,
        'applications' => [['invoice_id' => $this->i2->id, 'amount' => 100_000_00]],
    ]));

    expect(app(CustomerSummary::class)->build($this->customer, '2026-01-01', '2026-06-30'))
        ->toMatchArray(['balance' => 500_000_00, 'open_invoices' => 1])
        ->and(app(CustomerSummary::class)->build($this->customer, '2026-01-01', '2026-06-29'))
        ->toMatchArray(['balance' => 600_000_00, 'open_invoices' => 2]);
});

it('leaves opening balances out of what was invoiced this year', function () {
    app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => '2026-01-01',
        'due_date' => '2026-01-31', 'is_opening' => true,
        'lines' => [['description' => 'Opening balance', 'qty' => '1', 'unit_price' => 40_000_00,
            'tax_code_id' => TaxCode::query()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->value('id'),
            'income_account_id' => account($this->company, '4100')->id]],
    ]));

    expect(app(CustomerSummary::class)->build($this->customer, '2026-01-01', '2026-06-30'))->toMatchArray([
        'balance' => 640_000_00, 'overdue' => 540_000_00, 'open_invoices' => 3, 'invoiced' => 850_000_00,
    ]);
});

it('sums what is owed to a vendor, what is past due and what they billed', function () {
    expect(app(VendorSummary::class)->build($this->vendor, '2026-01-01', '2026-06-30'))->toBe([
        'balance' => 1_710_000_00,
        'overdue' => 1_500_000_00,
        'open_bills' => 2,
        'overdue_bills' => 1,
        'billed' => 2_210_000_00,
        'ewt' => 0,
        'last_payment' => ['date' => '2026-06-15', 'amount' => 500_000_00],
    ]);

    // The day before the payment, all of B1 was owed.
    expect(app(VendorSummary::class)->build($this->vendor, '2026-01-01', '2026-06-14'))->toMatchArray([
        'balance' => 2_210_000_00, 'overdue' => 2_000_000_00, 'open_bills' => 2, 'last_payment' => null,
    ]);
});

it('values stock on hand and totals what was sold and bought', function () {
    expect(app(ItemSummary::class)->build($this->rice, '2026-01-01', '2026-06-30'))->toBe([
        'on_hand' => ['qty_units' => 760 * 10_000, 'avg_cost_x10000' => 2_012_50 * 10_000, 'value' => 1_529_500_00],
        'sold_units' => 340 * 10_000,
        'sales' => 850_000_00,
        'bought_units' => 1_100 * 10_000,
        'purchases' => 2_210_000_00,
    ]);
});

it('reads an account balance and the period movement from the ledger', function () {
    // Stock at average cost equals the inventory account.
    expect(app(AccountSummary::class)->build(account($this->company, '1300'), '2026-01-01', '2026-06-30'))->toBe([
        'balance' => 1_529_500_00, 'debits' => 2_210_000_00, 'credits' => 680_500_00, 'last_entry' => '2026-06-25',
    ]);

    // Before June: only B1.
    expect(app(AccountSummary::class)->build(account($this->company, '1300'), '2026-06-01', '2026-06-30'))->toMatchArray([
        'debits' => 210_000_00, 'credits' => 680_500_00,
    ]);
});

it('shows a customer with their balance, invoices and payments', function () {
    Livewire::test(ViewCustomer::class, ['record' => $this->customer->getKey()])
        ->assertOk()
        ->assertSeeInOrder(['Balance due', '₱600,000.00', '2 open invoices'])
        ->assertSeeInOrder(['Overdue', '₱500,000.00', '1 invoice past due'])
        ->assertSeeInOrder(['Invoiced this year', '₱850,000.00'])
        ->assertSeeInOrder(['Last payment', '₱250,000.00', 'Received Jun 28, 2026'])
        ->assertActionVisible('edit');

    Livewire::test(InvoicesRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->i2, $this->i1], inOrder: true)
        ->assertSee('₱500,000.00') // still owed on I1
        ->assertSee('Partially paid');

    Livewire::test(CustomerPaymentsRelationManager::class, ['ownerRecord' => $this->customer, 'pageClass' => ViewCustomer::class])
        ->assertOk()
        ->assertSee($this->i1->number)
        ->assertSee('₱250,000.00');
});

it('lists a record history on the view page only, not on the edit form', function () {
    expect(InvoicesRelationManager::canViewForRecord($this->customer, ViewCustomer::class))->toBeTrue()
        ->and(InvoicesRelationManager::canViewForRecord($this->customer, EditCustomer::class))->toBeFalse();

    Livewire::test(ListCustomers::class)->assertTableActionVisible('view', $this->customer);
});

it('shows a vendor with their balance, bills and payments', function () {
    Livewire::test(ViewVendor::class, ['record' => $this->vendor->getKey()])
        ->assertOk()
        ->assertSeeInOrder(['Balance owed', '₱1,710,000.00', '2 open bills'])
        ->assertSeeInOrder(['Overdue', '₱1,500,000.00', '1 bill past due'])
        ->assertSeeInOrder(['Billed this year', '₱2,210,000.00'])
        ->assertSeeInOrder(['Last payment', '₱500,000.00', 'Paid Jun 15, 2026']);

    Livewire::test(BillsRelationManager::class, ['ownerRecord' => $this->vendor, 'pageClass' => ViewVendor::class])
        ->assertOk()
        ->assertCanSeeTableRecords([$this->b2, $this->b1], inOrder: true)
        ->assertSee('₱1,500,000.00');

    Livewire::test(VendorPaymentsRelationManager::class, ['ownerRecord' => $this->vendor, 'pageClass' => ViewVendor::class])
        ->assertOk()
        ->assertSee($this->b1->number);
});

it('shows an item with stock on hand and its sales and purchases', function () {
    Livewire::test(ViewItem::class, ['record' => $this->rice->getKey()])
        ->assertOk()
        ->assertSeeInOrder(['On hand', '760', 'Average cost ₱2,012.50 per sack_25kg'])
        ->assertSeeInOrder(['Stock value', '₱1,529,500.00'])
        ->assertSeeInOrder(['Sold this year', '₱850,000.00', '340 sack_25kg'])
        ->assertSeeInOrder(['Bought this year', '₱2,210,000.00', '1,100 sack_25kg'])
        ->assertSee('1300 · Inventory');

    $sales = $this->i1->lines()->get()->merge($this->i2->lines()->get());
    Livewire::test(SalesRelationManager::class, ['ownerRecord' => $this->rice, 'pageClass' => ViewItem::class])
        ->assertOk()
        ->assertCanSeeTableRecords($sales)
        ->assertSee($this->customer->name);

    Livewire::test(PurchasesRelationManager::class, ['ownerRecord' => $this->rice, 'pageClass' => ViewItem::class])
        ->assertOk()
        ->assertCanSeeTableRecords($this->b1->lines()->get()->merge($this->b2->lines()->get()))
        ->assertSee($this->vendor->name);
});

it('shows an account with its balance, movement and ledger', function () {
    $inventory = account($this->company, '1300');

    Livewire::test(ViewAccount::class, ['record' => $inventory->getKey()])
        ->assertOk()
        ->assertSeeInOrder(['Balance', '₱1,529,500.00', 'Debit balance as of Jun 30, 2026'])
        ->assertSeeInOrder(['Debits this year', '₱2,210,000.00', 'Credits this year', '₱680,500.00'])
        ->assertActionHasUrl('generalLedger', GeneralLedger::getUrl(['account' => $inventory->id]));

    Livewire::test(ViewAccount::class, ['record' => account($this->company, '4100')->getKey()])
        ->assertSeeInOrder(['Net this year', '₱850,000.00', 'Net credit since Jan 1, 2026'])
        ->assertDontSee('opposite to normal');

    Livewire::test(LedgerActivity::class, ['record' => $inventory])
        ->assertOk()
        ->assertCanSeeTableRecords(JournalLine::query()->where('account_id', $inventory->id)->get())
        ->assertCountTableRecords(4);
});

it('opens the general ledger on the account it was sent', function () {
    $inventory = account($this->company, '1300');

    Livewire::withQueryParams(['account' => $inventory->id])
        ->test(GeneralLedger::class)
        ->assertSet('entity', (string) $inventory->id)
        ->assertSee('1,529,500.00');
});

it('shows a bank account with its book balance and ledger', function () {
    Livewire::test(ViewBankAccount::class, ['record' => $this->bank->getKey()])
        ->assertOk()
        ->assertSee('BDO Unibank 0045-1234-5678')
        ->assertSeeInOrder(['Balance per books', '₱750,000.00'])
        ->assertSeeInOrder(['Money in this year', '₱1,250,000.00', 'Money out this year', '₱500,000.00'])
        ->assertSeeInOrder(['Last reconciled', 'Never'])
        ->assertSee('1120 · Cash in Bank');

    Livewire::test(LedgerActivity::class, ['record' => $this->bank])
        ->assertOk()
        ->assertCountTableRecords(3);
});
