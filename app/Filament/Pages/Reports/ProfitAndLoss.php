<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ProfitAndLossReport;

class ProfitAndLoss extends ReportPage
{
    protected static ?string $navigationLabel = 'Profit & Loss';

    protected static ?int $navigationSort = 2;

    protected function payload(): array
    {
        $r = app(ProfitAndLossReport::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);
        $rows = [['INCOME', '']];
        foreach ($r['income'] as $l) {
            $rows[] = [$l['code'].' '.$l['name'], $this->peso($l['amount'])];
        }
        $rows[] = ['Total income', $this->peso($r['total_income'])];
        $rows[] = ['EXPENSES', ''];
        foreach ($r['expense'] as $l) {
            $rows[] = [$l['code'].' '.$l['name'], $this->peso($l['amount'])];
        }
        $rows[] = ['Total expenses', $this->peso($r['total_expense'])];

        return [
            'columns' => ['Account', 'Amount'],
            'rows' => $rows,
            'totals' => ['NET INCOME', $this->peso($r['net_income'])],
        ];
    }
}
