<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ArAgingReport;

class ArAging extends ReportPage
{
    protected static ?string $navigationLabel = 'AR Aging';

    protected static ?int $navigationSort = 4;

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $r = app(ArAgingReport::class)->build($this->company()->id, (string) $this->asOf);
        $rows = array_map(fn ($x) => [$x['number'], $x['customer'], $x['due_date'], $x['bucket'], $this->peso($x['outstanding'])], $r['rows']);

        return [
            'columns' => ['Invoice', 'Customer', 'Due', 'Bucket', 'Outstanding'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($r['total'])],
        ];
    }
}
