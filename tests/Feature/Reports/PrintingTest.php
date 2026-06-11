<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Services\Printing\PrintInvoice;
use App\Services\Printing\ReportExporter;
use App\Services\Reports\SalesBook;
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
