<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\CashDisbursementsBook;

class CashDisbursementsBookPage extends ReportPage
{
    protected static ?string $navigationLabel = 'Cash Disbursements Book';

    protected static ?int $navigationSort = 14;

    public function getTitle(): string
    {
        return 'Cash Disbursements Book';
    }

    protected function payload(): array
    {
        $r = app(CashDisbursementsBook::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        $rows = array_map(fn ($x) => [
            (string) $x['date'], (string) $x['reference'], (string) ($x['voucher_no'] ?? ''), (string) $x['particulars'], $this->peso($x['amount']),
        ], $r['rows']);

        return [
            'columns' => ['Date', 'Reference', 'Voucher', 'Particulars', 'Amount'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($r['total'])],
        ];
    }
}
