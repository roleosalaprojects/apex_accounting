<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\CashFlowReport;

class CashFlow extends ReportPage
{
    protected static ?string $navigationLabel = 'Cash Flow Statement';

    protected static ?int $navigationSort = 10;

    protected function payload(): array
    {
        $r = app(CashFlowReport::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        return [
            'columns' => ['Section', 'Amount'],
            'rows' => [
                ['Cash flows from operating activities', $this->peso($r['operating'])],
                ['Cash flows from investing activities', $this->peso($r['investing'])],
                ['Cash flows from financing activities', $this->peso($r['financing'])],
            ],
            'totals' => ['NET CHANGE IN CASH', $this->peso($r['net_change'])],
            'meta' => [
                'ok' => $r['balanced'],
                'label' => $r['balanced']
                    ? 'Ties to the change in cash balances ✓'
                    : 'Does NOT tie to the change in cash balances (actual '.$this->peso($r['cash_change']).')',
            ],
        ];
    }
}
