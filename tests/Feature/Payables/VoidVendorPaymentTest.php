<?php

declare(strict_types=1);

use App\Actions\Payables\PayBill;
use App\Actions\Payables\PostBill;
use App\Actions\Payables\VoidVendorPayment;
use App\Data\Payables\BillData;
use App\Data\Payables\PayBillData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\JournalStatus;
use App\Enums\VatBucket;
use App\Filament\Resources\VendorPayments\Pages\ListVendorPayments;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\WithholdingCode;
use App\Services\Reports\EwtSummaryReport;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->landlord = Vendor::factory()->create([
        'company_id' => $this->company->id, 'name' => 'Landlord',
        'default_withholding_code_id' => WithholdingCode::query()->where('company_id', $this->company->id)->where('code', 'WC100')->value('id'),
    ]);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    // ₱56,000 rent, VAT inclusive: ₱50,000 base, ₱6,000 VAT, 5% EWT on the base.
    $this->bill = app(PostBill::class)->handle(BillData::from([
        'company_id' => $this->company->id, 'vendor_id' => $this->landlord->id, 'bill_date' => '2026-06-05', 'pricing_mode' => 'vat_inclusive',
        'lines' => [['description' => 'Office rent', 'qty' => '1', 'unit_price' => 56_000_00, 'tax_code_id' => $vat12,
            'vat_bucket' => VatBucket::Common->value, 'expense_or_asset_account_id' => account($this->company, '6100')->id]],
    ]));
});

function payRent(int $amount, string $date = '2026-06-20'): VendorPayment
{
    return app(PayBill::class)->handle(PayBillData::from([
        'company_id' => test()->company->id, 'vendor_id' => test()->landlord->id, 'payment_date' => $date,
        'paid_from_account_id' => account(test()->company, '1120')->id,
        'applications' => [['bill_id' => test()->bill->id, 'amount' => $amount]],
    ]));
}

function ledgerOf(string $code): int
{
    return (int) DB::table('journal_lines')
        ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
        ->where('journal_entries.company_id', test()->company->id)
        ->where('journal_lines.account_id', account(test()->company, $code)->id)
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as b')->value('b');
}

it('voids a payment: the bill is owed again and the cash and EWT come back', function () {
    $payment = payRent(56_000_00);
    expect($this->bill->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(ledgerOf('1120'))->toBe(-53_500_00)
        ->and(ledgerOf('2210'))->toBe(-2_500_00);

    $voided = app(VoidVendorPayment::class)->handle($payment, 'Check bounced', $this->owner);

    expect($voided->status)->toBe('voided')
        ->and($payment->fresh()->journalEntry->status)->toBe(JournalStatus::Reversed)
        ->and($this->bill->fresh()->status)->toBe(InvoiceStatus::Posted)
        ->and($this->bill->fresh()->outstanding())->toBe(56_000_00)
        ->and(ledgerOf('1120'))->toBe(0)
        ->and(ledgerOf('2210'))->toBe(0)
        ->and(ledgerOf('2100'))->toBe(-56_000_00)
        ->and(app(EwtSummaryReport::class)->build($this->company->id, '2026-06-01', '2026-06-30')['total_ewt'])->toBe(0)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);

    expect(fn () => app(VoidVendorPayment::class)->handle($voided, 'Again', $this->owner))
        ->toThrow(RuntimeException::class, 'Only a posted payment can be voided.');
});

it('leaves earlier payments on the bill when a later one is voided', function () {
    payRent(20_000_00, '2026-06-15');
    $second = payRent(36_000_00, '2026-06-20');
    expect($this->bill->fresh()->status)->toBe(InvoiceStatus::Paid);

    app(VoidVendorPayment::class)->handle($second, 'Paid twice', $this->owner);

    expect($this->bill->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($this->bill->fresh()->outstanding())->toBe(36_000_00);
});

it('voids a payment from the Pay Bills list', function () {
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
    $payment = payRent(56_000_00);

    Livewire::test(ListVendorPayments::class)
        ->assertTableActionVisible('void', $payment)
        ->callTableAction('void', $payment, data: ['reason' => 'Check bounced'])
        ->assertHasNoTableActionErrors()
        ->assertTableActionHidden('void', $payment->fresh());

    expect($payment->fresh()->status)->toBe('voided')
        ->and($this->bill->fresh()->status)->toBe(InvoiceStatus::Posted);
});
