<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\BalanceSheetReport;

class BalanceSheet extends ReportPage
{
    protected static ?string $navigationLabel = 'Balance Sheet';

    protected static ?int $navigationSort = 3;

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $r = app(BalanceSheetReport::class)->build($this->company(), (string) $this->asOf);
        $rows = [['ASSETS', '']];
        foreach ($r['assets'] as $l) {
            $rows[] = [trim(($l['code'] ?? '').' '.$l['name']), $this->peso($l['amount'])];
        }
        $rows[] = ['Total assets', $this->peso($r['total_assets'])];
        $rows[] = ['LIABILITIES', ''];
        foreach ($r['liabilities'] as $l) {
            $rows[] = [trim(($l['code'] ?? '').' '.$l['name']), $this->peso($l['amount'])];
        }
        $rows[] = ['Total liabilities', $this->peso($r['total_liabilities'])];
        $rows[] = ['EQUITY', ''];
        foreach ($r['equity'] as $l) {
            $rows[] = [trim(($l['code'] ?? '').' '.$l['name']), $this->peso($l['amount'])];
        }
        $rows[] = ['Total equity', $this->peso($r['total_equity'])];

        return [
            'columns' => ['Account', 'Amount'],
            'rows' => $rows,
            'totals' => ['LIAB + EQUITY', $this->peso($r['total_liabilities'] + $r['total_equity'])],
            'meta' => ['ok' => $r['balanced'], 'label' => $r['balanced'] ? 'Balanced ✓' : 'OUT OF BALANCE'],
        ];
    }
}
