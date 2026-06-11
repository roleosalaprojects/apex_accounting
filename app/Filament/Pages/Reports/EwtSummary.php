<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\EwtSummaryReport;

class EwtSummary extends ReportPage
{
    protected static ?string $navigationLabel = 'EWT Summary';

    protected static ?int $navigationSort = 9;

    public function getTitle(): string
    {
        return 'EWT Summary';
    }

    protected function payload(): array
    {
        $r = app(EwtSummaryReport::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);
        $rows = array_map(fn ($x) => [$x['vendor'], $x['tin'], $x['atc'], number_format($x['rate_bp'] / 100, 2).'%', $this->peso($x['base']), $this->peso($x['ewt'])], $r['rows']);

        return [
            'columns' => ['Vendor', 'TIN', 'ATC', 'Rate', 'Base', 'EWT'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($r['total_base']), $this->peso($r['total_ewt'])],
        ];
    }
}
