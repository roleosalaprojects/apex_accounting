<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Fx\FxRevaluationReport;

class FxRevaluation extends ReportPage
{
    protected static ?string $navigationLabel = 'FX Revaluation';

    protected static ?int $navigationSort = 17;

    public function getTitle(): string
    {
        return 'Unrealized FX Revaluation';
    }

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $r = app(FxRevaluationReport::class)->build($this->company()->id, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $rows[] = [
                (string) $x['type'],
                (string) $x['number'],
                $x['party'] !== null ? (string) $x['party'] : '—',
                (string) $x['currency'],
                number_format((int) $x['foreign_outstanding'] / 100, 2),
                $this->peso((int) $x['booked']),
                $this->peso((int) $x['revalued']),
                $this->peso((int) $x['unrealized']),
            ];
        }

        return [
            'columns' => ['Type', 'Document', 'Party', 'Ccy', 'Foreign o/s', 'Booked ₱', 'Revalued ₱', 'Unrealized gain/(loss)'],
            'rows' => $rows,
            'totals' => ['', '', '', '', '', '', 'TOTAL', $this->peso($r['total_unrealized'])],
        ];
    }
}
