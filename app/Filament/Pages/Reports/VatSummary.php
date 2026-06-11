<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\VatSummaryReport;
use Illuminate\Support\Carbon;

class VatSummary extends ReportPage
{
    protected static ?string $navigationLabel = '2550Q VAT Summary';

    protected static ?int $navigationSort = 8;

    public function getTitle(): string
    {
        return '2550Q VAT Summary';
    }

    protected function payload(): array
    {
        $asOf = Carbon::parse($this->asOf);
        $quarter = (int) ceil($asOf->month / 3);
        $r = app(VatSummaryReport::class)->build($this->company()->id, $asOf->year, $quarter, (string) $this->from, (string) $this->asOf);

        $rows = [
            ['VAT-exempt sales', $this->peso($r['exempt_sales'])],
            ['Zero-rated sales', $this->peso($r['zero_rated_sales'])],
            ['VATable sales', $this->peso($r['vatable_sales'])],
            ['Output VAT', $this->peso($r['output_vat'])],
            ['Creditable input VAT (direct + allocated common)', $this->peso($r['creditable_input_vat'])],
            ['VAT payable', $this->peso($r['vat_payable'])],
            ['Excess input VAT carryover', $this->peso($r['carryover'])],
        ];

        return [
            'columns' => ['Line', 'Amount'],
            'rows' => $rows,
            'meta' => ['label' => 'Quarter '.$quarter.' '.$asOf->year.($r['allocation_id'] ? ' · allocation #'.$r['allocation_id'] : '')],
        ];
    }
}
