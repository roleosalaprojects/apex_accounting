<?php

declare(strict_types=1);

use App\Actions\Receivables\ApplyCreditMemo;
use App\Actions\Receivables\PostCreditMemo;
use App\Actions\Receivables\PostInvoice;
use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Actions\Receivables\VoidInvoice;
use App\Data\Receivables\CreditMemoData;
use App\Data\Receivables\CustomerPaymentData;
use App\Data\Receivables\InvoiceData;
use App\Models\Customer;
use App\Models\TaxCode;
use App\Services\Reports\StatementOfAccount;

it('lists invoices, payments and credit memos, and leaves voided invoices out', function () {
    $company = makeCompany();
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $vat12 = TaxCode::query()->where('company_id', $company->id)->where('code', 'VAT12')->value('id');
    $invoice = fn (string $date, int $unit) => app(PostInvoice::class)->handle(InvoiceData::from([
        'company_id' => $company->id, 'customer_id' => $customer->id, 'invoice_date' => $date,
        'lines' => [['description' => 'POS', 'qty' => '1', 'unit_price' => $unit, 'tax_code_id' => $vat12,
            'income_account_id' => account($company, '4200')->id]],
    ]));

    $march = $invoice('2026-03-10', 22_400_00); // before the period: part of the opening balance
    $june = $invoice('2026-06-10', 112_000_00);
    $voided = $invoice('2026-06-12', 5_600_00);
    app(VoidInvoice::class)->handle($voided, 'Issued in error');

    $memo = app(PostCreditMemo::class)->handle(CreditMemoData::from([
        'company_id' => $company->id, 'customer_id' => $customer->id, 'memo_date' => '2026-06-20',
        'lines' => [['description' => 'Returned unit', 'qty' => '1', 'unit_price' => 11_200_00, 'tax_code_id' => $vat12,
            'income_account_id' => account($company, '4200')->id]],
    ]));
    app(ApplyCreditMemo::class)->handle($memo, [['invoice_id' => $june->id, 'amount' => 11_200_00]]);

    app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
        'company_id' => $company->id, 'customer_id' => $customer->id, 'payment_date' => '2026-06-25',
        'deposit_to_account_id' => account($company, '1120')->id, 'amount' => 50_000_00,
        'applications' => [['invoice_id' => $june->id, 'amount' => 50_000_00]],
    ]));

    $soa = app(StatementOfAccount::class)->build($customer, '2026-06-01', '2026-06-30');

    expect($soa['opening'])->toBe(22_400_00)
        ->and(array_map(fn (array $r): array => [$r['type'], $r['charge'], $r['credit'], $r['balance']], $soa['rows']))->toBe([
            ['Invoice', 112_000_00, 0, 134_400_00],
            ['Credit memo', 0, 11_200_00, 123_200_00],
            ['Payment', 0, 50_000_00, 73_200_00],
        ])
        ->and($soa['closing'])->toBe(73_200_00)
        ->and($march->fresh()->outstanding() + $june->fresh()->outstanding())->toBe(73_200_00);
});
