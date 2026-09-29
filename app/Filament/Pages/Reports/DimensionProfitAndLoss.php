<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\DimensionProfitAndLossReport;

/**
 * Profit and loss split by department, project, fund or branch — the tags on
 * each journal line. Untagged activity has its own row so totals tie to the
 * Profit & Loss report.
 */
class DimensionProfitAndLoss extends ReportPage
{
    protected static ?string $navigationLabel = 'P&L by Dimension';

    protected static ?string $title = 'Profit & Loss by Dimension';

    protected static ?int $navigationSort = 3;

    public function mount(): void
    {
        parent::mount();

        $this->entity = 'department';
    }

    protected function entityFilter(): ?array
    {
        return [
            'label' => 'Split by',
            'options' => ['department' => 'Department', 'project' => 'Project', 'fund' => 'Fund', 'branch' => 'Branch'],
        ];
    }

    protected function payload(): array
    {
        $dimension = isset(DimensionProfitAndLossReport::DIMENSIONS[(string) $this->entity]) ? (string) $this->entity : 'department';
        $r = app(DimensionProfitAndLossReport::class)->build($this->company()->id, $dimension, (string) $this->from, (string) $this->asOf);

        return [
            'columns' => [ucfirst($dimension), 'Income', 'Expenses', 'Net income'],
            'rows' => array_map(fn (array $row): array => [
                $row['code'] !== null ? "{$row['code']} · {$row['name']}" : $row['name'],
                $this->peso($row['income']), $this->peso($row['expenses']), $this->peso($row['net']),
            ], $r['rows']),
            'totals' => ['TOTAL', $this->peso($r['total_income']), $this->peso($r['total_expense']), $this->peso($r['net_income'])],
        ];
    }
}
