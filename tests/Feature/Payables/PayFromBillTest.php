<?php

declare(strict_types=1);

use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\VatBucket;
use App\Filament\Resources\Bills\Pages\ListBills;
use App\Filament\Resources\Bills\Pages\ViewBill;
use App\Filament\Resources\VendorPayments\Pages\CreateVendorPayment;
use App\Models\Bill;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\WithholdingCode;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->actingAs(makeUserWithRole($this->company, CompanyRole::Owner));
    Filament::setTenant($this->company);

    $this->landlord = Vendor::factory()->create([
        'company_id' => $this->company->id, 'name' => 'Landlord',
        'default_withholding_code_id' => WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC100')->value('id'),
    ]);
    $this->vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');
});

function rentBill(Vendor $vendor): Bill
{
    return app(PostBill::class)->handle(BillData::from([
        'company_id' => test()->company->id, 'vendor_id' => $vendor->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
        'lines' => [['description' => 'Office rent', 'qty' => '1', 'unit_price' => 56_000_00, 'tax_code_id' => test()->vat12,
            'vat_bucket' => VatBucket::Common->value, 'expense_or_asset_account_id' => account(test()->company, '6100')->id]],
    ]));
}

it('pays a bill from its own page, withholding the vendor\'s EWT', function () {
    $bill = rentBill($this->landlord);

    Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
        ->assertActionVisible('pay')
        ->mountAction('pay')
        ->assertActionDataSet(['amount' => '56000.00']) // the balance, ready to pay in full
        ->setActionData(['payment_date' => '2026-06-20', 'method' => 'check', 'paid_from_account_id' => account($this->company, '1120')->id])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Paid Landlord ₱53,500.00')
        ->assertActionHidden('pay');

    $payment = VendorPayment::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->sole();

    expect($bill->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($payment->gross_applied->minor)->toBe(56_000_00)
        ->and($payment->ewt->minor)->toBe(2_500_00) // 5% of the ₱50,000 VAT-exclusive rent
        ->and($payment->net_paid->minor)->toBe(53_500_00);
});

it('pays part of a bill and offers the rest next time', function () {
    $bill = rentBill($this->landlord);

    Livewire::test(ViewBill::class, ['record' => $bill->getRouteKey()])
        ->callAction('pay', data: ['payment_date' => '2026-06-20', 'method' => 'bank',
            'paid_from_account_id' => account($this->company, '1120')->id, 'amount' => '20000'])
        ->assertHasNoActionErrors()
        ->assertActionVisible('pay')
        ->mountAction('pay')
        ->assertActionDataSet(['amount' => '36000.00']);

    expect($bill->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
});

it('offers Pay on open bills in the list', function () {
    $open = rentBill($this->landlord);
    $paid = rentBill($this->landlord);
    app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->landlord->id, 'payment_date' => '2026-06-20',
        'paid_from_account_id' => account($this->company, '1120')->id,
        'applications' => [['bill_id' => $paid->id, 'amount' => 56_000_00]],
    ]));

    Livewire::test(ListBills::class)
        ->assertTableActionVisible('pay', $open)
        ->assertTableActionHidden('pay', $paid);
});

it('refuses to apply a payment to another vendor\'s bill', function () {
    $other = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Someone else']);
    $bill = rentBill($other);

    expect(fn () => app(PayBill::class)->handle(PayBillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->landlord->id, 'payment_date' => '2026-06-20',
        'paid_from_account_id' => account($this->company, '1120')->id,
        'applications' => [['bill_id' => $bill->id, 'amount' => 56_000_00]],
    ])))->toThrow(RuntimeException::class, "Bill {$bill->number} is not from Landlord.");

    expect(VendorPayment::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->count())->toBe(0);
});

it('lists only the chosen vendor\'s open bills on the Pay Bills form', function () {
    Repeater::fake();
    $mine = rentBill($this->landlord);
    $theirs = rentBill(Vendor::factory()->create(['company_id' => $this->company->id]));

    Livewire::test(CreateVendorPayment::class)
        ->assertFormFieldExists('applications.0.bill_id', fn (Select $field): bool => $field->getOptions() === [])
        ->fillForm(['vendor_id' => $this->landlord->id])
        ->assertFormFieldExists('applications.0.bill_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$mine->id]);

    expect($theirs->vendor_id)->not->toBe($this->landlord->id);
});
