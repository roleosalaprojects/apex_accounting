<?php

declare(strict_types=1);

use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Actions\Receivables\VoidCustomerPayment;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Enums\CompanyRole;
use App\Enums\InvoiceStatus;
use App\Enums\JournalStatus;
use App\Filament\Resources\CustomerPayments\Pages\ListCustomerPayments;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\TaxCode;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = makeCompany();
    $this->owner = makeUserWithRole($this->company, CompanyRole::Owner);
    $this->customer = Customer::factory()->create(['company_id' => $this->company->id, 'name' => 'Dari Ventures']);
    $vat12 = TaxCode::query()->where('company_id', $this->company->id)->where('code', 'VAT12')->value('id');

    $this->invoice = app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $this->company->id, 'customer_id' => $this->customer->id, 'invoice_date' => '2026-06-10',
        'lines' => [['description' => 'Consulting', 'qty' => '1', 'unit_price' => 112_000_00,
            'tax_code_id' => $vat12, 'income_account_id' => account($this->company, '4200')->id]],
    ]));
});

function collect112k(): CustomerPayment
{
    // The customer withholds ₱2,000 EWT and pays ₱110,000 cash.
    return app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => test()->company->id, 'customer_id' => test()->customer->id, 'payment_date' => '2026-06-25',
        'deposit_to_account_id' => account(test()->company, '1120')->id, 'amount' => 110_000_00, 'ewt_withheld' => 2_000_00,
        'applications' => [['invoice_id' => test()->invoice->id, 'amount' => 112_000_00]],
    ]));
}

function bankBalance(): int
{
    return (int) DB::table('journal_lines')
        ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
        ->where('journal_entries.company_id', test()->company->id)
        ->where('journal_lines.account_id', account(test()->company, '1120')->id)
        ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as b')->value('b');
}

it('voids a collection: the invoice is open again and the cash leaves the bank', function () {
    $payment = collect112k();
    expect($this->invoice->fresh()->status)->toBe(InvoiceStatus::Paid)->and(bankBalance())->toBe(110_000_00);

    $voided = app(VoidCustomerPayment::class)->handle($payment, 'Deposit was returned', $this->owner);

    expect($voided->status)->toBe('voided')
        ->and($payment->fresh()->journalEntry->status)->toBe(JournalStatus::Reversed)
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Posted)
        ->and($this->invoice->fresh()->outstanding())->toBe(112_000_00)
        ->and(bankBalance())->toBe(0)
        ->and(Artisan::call('ledger:verify', ['company' => $this->company->id]))->toBe(0);
});

it('voids a collection from the Receive Payments list', function () {
    $this->actingAs($this->owner);
    Filament::setTenant($this->company);
    $payment = collect112k();

    Livewire::test(ListCustomerPayments::class)
        ->callTableAction('void', $payment, data: ['reason' => 'Deposit was returned'])
        ->assertHasNoTableActionErrors()
        ->assertTableActionHidden('void', $payment->fresh());

    expect($payment->fresh()->status)->toBe('voided')
        ->and($this->invoice->fresh()->status)->toBe(InvoiceStatus::Posted);
});
