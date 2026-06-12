<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\CashReceiptsBook;

class CashReceiptsBookPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Cash Receipts Book';

    protected static ?int $navigationSort = 13;

    public function getTitle(): string
    {
        return 'Cash Receipts Book';
    }

    protected function payload(): array
    {
        $r = app(CashReceiptsBook::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        $rows = array_map(fn ($x) => [
            (string) $x['date'], (string) $x['reference'], (string) $x['particulars'], $this->peso($x['amount']),
        ], $r['rows']);

        return [
            'columns' => ['Date', 'Reference', 'Particulars', 'Amount'],
            'rows' => $rows,
            'totals' => ['', '', 'TOTAL', $this->peso($r['total'])],
        ];
    }
}
