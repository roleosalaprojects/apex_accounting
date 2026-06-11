<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\TrialBalanceReport;

class TrialBalance extends ReportPage
{
    protected static ?string $navigationLabel = 'Trial Balance';

    protected static ?int $navigationSort = 1;

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $r = app(TrialBalanceReport::class)->build($this->company()->id, (string) $this->asOf);
        $rows = array_map(fn ($x) => [$x['code'], $x['name'], $this->peso($x['debit']), $this->peso($x['credit'])], $r['rows']);

        return [
            'columns' => ['Code', 'Account', 'Debit', 'Credit'],
            'rows' => $rows,
            'totals' => ['', 'TOTAL', $this->peso($r['total_debit']), $this->peso($r['total_credit'])],
            'meta' => ['ok' => $r['balanced'], 'label' => $r['balanced'] ? 'Balanced ✓' : 'OUT OF BALANCE'],
        ];
    }
}
