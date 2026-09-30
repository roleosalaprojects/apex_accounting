<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\TaxCode;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\Printing\PrintCollectionReceipt;
use App\Services\Printing\PrintCustomerStatement;
use App\Services\Printing\PrintInvoice;
use App\Services\Printing\PrintOrder;
use App\Services\Printing\PrintPaymentVoucher;
use App\Services\Printing\ReportExporter;
use App\Services\Reports\SalesBook;
use App\Support\AmountInWords;
use Database\Seeders\DemoCompanySeeder;

beforeEach(function () {
    $this->company = (new DemoCompanySeeder)->build();
});

it('renders the invoice print template to a PDF', function () {
    $invoice = Invoice::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->orderBy('id')->first();

    $pdf = app(PrintInvoice::class)->render($invoice);

    expect($pdf)->toBeString()
        ->and(str_starts_with($pdf, '%PDF'))->toBeTrue()
        ->and(strlen($pdf))->toBeGreaterThan(1000);
});

it('exports a BIR book to XLSX and PDF', function () {
    $sales = app(SalesBook::class)->build($this->company->id, '2026-04-01', '2026-06-30');

    $rows = array_map(fn ($r) => [
        $r['date'], $r['number'], $r['customer'], $r['exempt'] / 100, $r['vatable'] / 100, $r['output_vat'] / 100, $r['total'] / 100,
    ], $sales['rows']);

    $header = 'Dari Ventures Corp. — TIN 009-123-456-00000';
    $headers = ['Date', 'Invoice No.', 'Customer', 'Exempt', 'VATable', 'Output VAT', 'Total'];

    $xlsx = app(ReportExporter::class)->toXlsx($header, 'Sales Book', $headers, $rows);
    $pdf = app(ReportExporter::class)->toPdf($header, 'Sales Book', $headers, $rows);

    expect(str_starts_with($xlsx, 'PK'))->toBeTrue()       // XLSX = zip
        ->and(strlen($xlsx))->toBeGreaterThan(1000)
        ->and(str_starts_with($pdf, '%PDF'))->toBeTrue();
});

it('prints a collection receipt: amount in words, the invoices settled, and the CR number', function () {
    $payment = CustomerPayment::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('status', 'posted')->orderBy('id')->firstOrFail();

    $data = app(PrintCollectionReceipt::class)->data($payment);
    $html = view('print.collection-receipt', $data)->render();

    expect($payment->fresh()->collection_receipt_no)->toStartWith('CR-2026-')
        ->and($html)->toContain('COLLECTION RECEIPT')
        ->toContain($payment->customer->name)
        ->toContain('PESOS AND')
        ->toContain($data['applications'][0]['number'])
        ->toContain('not valid for claiming input taxes');
    expect(str_starts_with(app(PrintCollectionReceipt::class)->render($payment), '%PDF'))->toBeTrue();
});

it('prints a payment voucher with the EWT withheld and the net paid in words', function () {
    $payment = VendorPayment::query()->withoutGlobalScopes()
        ->where('company_id', $this->company->id)->where('ewt', '>', 0)->orderBy('id')->firstOrFail();

    $html = view('print.payment-voucher', app(PrintPaymentVoucher::class)->data($payment))->render();

    expect($html)->toContain('PAYMENT VOUCHER')
        ->toContain($payment->vendor->name)
        ->toContain('WC100')
        ->toContain(AmountInWords::pesos($payment->net_paid->minor))
        ->toContain($payment->journalEntry->number);
    expect(str_starts_with(app(PrintPaymentVoucher::class)->render($payment), '%PDF'))->toBeTrue();
});

it('prints purchase and sales orders with their totals', function () {
    $vendor = Vendor::factory()->create(['company_id' => $this->company->id, 'name' => 'Isabela Rice Mill Corp.']);
    $exempt = TaxCode::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->where('code', 'EXEMPT')->firstOrFail();
    $po = PurchaseOrder::factory()->create(['company_id' => $this->company->id, 'vendor_id' => $vendor->id, 'number' => 'PO-2026-000007',
        'order_date' => '2026-06-01', 'pricing_mode' => 'vat_exclusive']);
    $po->lines()->create(['description' => 'Rice 25kg', 'qty' => '100', 'unit_price' => 2_000_00, 'tax_code_id' => $exempt->id,
        'expense_account_id' => account($this->company, '1300')->id]);

    $html = view('print.order', app(PrintOrder::class)->data($po, [
        'title' => 'PURCHASE ORDER', 'partyLabel' => 'Supplier', 'party' => $vendor, 'secondDateLabel' => 'Expected', 'secondDate' => null,
        'acknowledgeLabel' => 'Accepted by (supplier)',
    ]))->render();

    expect($html)->toContain('PURCHASE ORDER')->toContain('PO-2026-000007')->toContain('Isabela Rice Mill Corp.')
        ->toContain('Rice 25kg')->toContain('₱200,000.00')->toContain('TWO HUNDRED THOUSAND PESOS AND 00/100');
    expect(str_starts_with(app(PrintOrder::class)->purchaseOrder($po), '%PDF'))->toBeTrue();

    $customer = Customer::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();
    $so = SalesOrder::factory()->create(['company_id' => $this->company->id, 'customer_id' => $customer->id, 'number' => 'SO-2026-000003',
        'order_date' => '2026-06-02', 'pricing_mode' => 'vat_inclusive']);
    $so->lines()->create(['description' => 'POS Terminal', 'qty' => '2', 'unit_price' => 56_000_00, 'tax_code_id' => $exempt->id,
        'income_account_id' => account($this->company, '4200')->id]);

    expect(str_starts_with(app(PrintOrder::class)->salesOrder($so), '%PDF'))->toBeTrue();
});

it('prints a customer statement with the open balance aged', function () {
    $customer = Customer::query()->withoutGlobalScopes()->where('company_id', $this->company->id)->firstOrFail();

    $data = app(PrintCustomerStatement::class)->data($customer, '2026-04-01', '2026-06-30');
    $html = view('print.statement', $data)->render();

    expect($html)->toContain('STATEMENT OF ACCOUNT')->toContain($customer->name)->toContain('Balance brought forward')
        ->toContain('Over 90 days')->toContain('Balance due')
        ->and($data['aging']['Total'])->toBe($data['closing']);
    expect(str_starts_with(app(PrintCustomerStatement::class)->render($customer, '2026-04-01', '2026-06-30'), '%PDF'))->toBeTrue();
});
