<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\StockSummaryReport;
use App\Support\Quantity;

/** Every stocked item over a period: opening, in, out, closing, and whether the closing value ties to the ledger. */
class StockSummaryPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Stock Summary';

    protected static ?int $navigationSort = 18;

    public function getTitle(): string
    {
        return 'Stock Summary';
    }

    protected function payload(): array
    {
        $columns = ['SKU', 'Item', 'Opening qty', 'Opening value', 'In qty', 'In value', 'Out qty', 'Out value', 'Closing qty', 'Avg cost', 'Closing value'];
        $r = app(StockSummaryReport::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $rows[] = [
                $x['sku'], $x['name'],
                Quantity::compact($x['opening_units']), $this->peso($x['opening_value']),
                Quantity::compact($x['in_units']), $this->peso($x['in_value']),
                Quantity::compact($x['out_units']), $this->peso($x['out_value']),
                Quantity::compact($x['closing_units']), $this->peso($x['avg_cost']), $this->peso($x['closing_value']),
            ];
        }

        $difference = $r['totals']['closing_value'] - $r['ledger_value'];

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['', 'TOTAL', '', $this->peso($r['totals']['opening_value']), '', $this->peso($r['totals']['in_value']), '', $this->peso($r['totals']['out_value']), '', '', $this->peso($r['totals']['closing_value'])],
            'meta' => [
                'ok' => $difference === 0,
                'label' => $difference === 0
                    ? 'Closing stock of ₱'.$this->peso($r['totals']['closing_value']).' ties to the ledger\'s inventory accounts as of '.$this->date($this->asOf).'.'
                    : 'Closing stock of ₱'.$this->peso($r['totals']['closing_value']).' differs from the ledger\'s inventory accounts (₱'.$this->peso($r['ledger_value']).') by ₱'.$this->peso($difference).' — run ledger:verify.',
            ],
        ];
    }
}
