<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\DunningReport;

class Dunning extends ReportPage
{
    protected static ?string $navigationLabel = 'Dunning / Overdue';

    protected static ?int $navigationSort = 16;

    public function getTitle(): string
    {
        return 'Dunning / Overdue Receivables';
    }

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $r = app(DunningReport::class)->build($this->company()->id, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $limit = (int) $x['credit_limit'];
            $status = $x['over_limit'] === true ? 'OVER LIMIT' : ((int) $x['overdue'] > 0 ? 'Overdue' : 'Within terms');

            $rows[] = [
                (string) $x['customer'],
                $this->peso((int) $x['outstanding']),
                $this->peso((int) $x['overdue']),
                $x['oldest_due'] !== null ? (string) $x['oldest_due'] : '—',
                $limit > 0 ? $this->peso($limit) : '—',
                $status,
            ];
        }

        return [
            'columns' => ['Customer', 'Outstanding', 'Overdue', 'Oldest due', 'Credit limit', 'Status'],
            'rows' => $rows,
            'totals' => ['TOTAL', $this->peso($r['total_outstanding']), $this->peso($r['total_overdue']), '', '', ''],
        ];
    }
}
