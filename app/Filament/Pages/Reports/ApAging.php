<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ApAgingReport;

class ApAging extends ReportPage
{
    protected static ?string $navigationLabel = 'AP Aging';

    protected static ?int $navigationSort = 5;

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $r = app(ApAgingReport::class)->build($this->company()->id, (string) $this->asOf);
        $rows = array_map(fn ($x) => [$x['number'], $x['vendor'], $x['due_date'], $x['bucket'], $this->peso($x['outstanding'])], $r['rows']);

        return [
            'columns' => ['Bill', 'Vendor', 'Due', 'Bucket', 'Outstanding'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($r['total'])],
        ];
    }
}
