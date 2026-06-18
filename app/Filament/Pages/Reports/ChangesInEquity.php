<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\StatementOfChangesInEquity;

class ChangesInEquity extends ReportPage
{
    protected static ?string $navigationLabel = 'Changes in Equity';

    protected static ?int $navigationSort = 4;

    public function getTitle(): string
    {
        return 'Statement of Changes in Equity';
    }

    protected function payload(): array
    {
        $r = app(StatementOfChangesInEquity::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $rows[] = [$x['code'].' '.$x['name'], $this->peso($x['opening']), $this->peso($x['movement']), $this->peso($x['closing'])];
        }
        $rows[] = ['Profit for the period (per Income Statement; closes to Retained Earnings at year-end)', '', $this->peso($r['net_income']), ''];

        return [
            'columns' => ['Equity component', 'Beginning', 'Net change', 'Ending'],
            'rows' => $rows,
            'totals' => ['TOTAL EQUITY', $this->peso($r['opening_total']), $this->peso($r['movement_total']), $this->peso($r['closing_total'])],
        ];
    }
}
