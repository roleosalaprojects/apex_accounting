<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\GeneralJournalReport;

class GeneralJournal extends ReportPage
{
    protected static ?string $navigationLabel = 'General Journal';

    protected static ?int $navigationSort = 12;

    protected function payload(): array
    {
        $r = app(GeneralJournalReport::class)->build($this->company()->id, (string) $this->from, (string) $this->asOf);

        $rows = array_map(fn ($x) => [
            (string) $x['date'], (string) $x['number'], (string) $x['account'], (string) $x['memo'],
            $this->peso($x['debit']), $this->peso($x['credit']),
        ], $r['rows']);

        return [
            'columns' => ['Date', 'JE No.', 'Account', 'Memo', 'Debit', 'Credit'],
            'rows' => $rows,
            'totals' => ['', '', '', 'TOTAL', $this->peso($r['total_debit']), $this->peso($r['total_credit'])],
        ];
    }
}
