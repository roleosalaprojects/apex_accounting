<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\PurchaseBook;

class PurchaseBookPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Purchase Book';

    protected static ?int $navigationSort = 7;

    public function getTitle(): string
    {
        return 'Purchase Book';
    }

    protected function payload(): array
    {
        $r = app(PurchaseBook::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);
        $rows = array_map(fn ($x) => [
            $x['date'], $x['number'], $x['vendor'], $x['tin'],
            $this->peso($x['exempt']), $this->peso($x['vatable']),
            $this->peso($x['input_vat_direct']), $this->peso($x['input_vat_common']), $this->peso($x['total']),
        ], $r['rows']);
        $t = $r['totals'];

        return [
            'columns' => ['Date', 'Bill', 'Vendor', 'TIN', 'Exempt', 'VATable', 'Input VAT (direct)', 'Input VAT (common)', 'Total'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($t['exempt']), $this->peso($t['vatable']), $this->peso($t['input_vat_direct']), $this->peso($t['input_vat_common']), $this->peso($t['total'])],
        ];
    }
}
