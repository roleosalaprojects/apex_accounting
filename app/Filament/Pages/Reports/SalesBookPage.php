<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\SalesBook;

class SalesBookPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Sales Book';

    protected static ?int $navigationSort = 6;

    public function getTitle(): string
    {
        return 'Sales Book';
    }

    protected function payload(): array
    {
        $r = app(SalesBook::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);
        $rows = array_map(fn ($x) => [
            $x['date'], $x['number'], $x['customer'], $x['tin'],
            $this->peso($x['exempt']), $this->peso($x['zero_rated']), $this->peso($x['vatable']),
            $this->peso($x['output_vat']), $this->peso($x['total']),
        ], $r['rows']);
        $t = $r['totals'];

        return [
            'columns' => ['Date', 'Invoice', 'Customer', 'TIN', 'Exempt', 'Zero', 'VATable', 'Output VAT', 'Total'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($t['exempt']), $this->peso($t['zero_rated']), $this->peso($t['vatable']), $this->peso($t['output_vat']), $this->peso($t['total'])],
        ];
    }
}
