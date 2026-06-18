<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Models\Budget;
use App\Services\Reports\BudgetVsActualReport;

class BudgetVsActual extends ReportPage
{
    protected static ?string $navigationLabel = 'Budget vs Actual';

    protected static ?int $navigationSort = 5;

    public function getTitle(): string
    {
        return 'Budget vs Actual';
    }

    protected function entityFilter(): ?array
    {
        return [
            'label' => 'Budget',
            'options' => Budget::query()
                ->orderByDesc('fiscal_year')->orderBy('name')->get()
                ->mapWithKeys(fn (Budget $b): array => [$b->id => "{$b->fiscal_year} — {$b->name}"])
                ->all(),
        ];
    }

    protected function payload(): array
    {
        $columns = ['Account', 'Budget', 'Actual', 'Variance', '% Used'];

        if (! $this->entity) {
            return ['columns' => $columns, 'rows' => [], 'meta' => ['label' => 'Select a budget to compare against actuals.']];
        }

        $r = app(BudgetVsActualReport::class)->build($this->company()->id, (int) $this->entity, (string) $this->from, (string) $this->asOf);

        $rows = [];
        foreach ($r['rows'] as $x) {
            $rows[] = [
                $x['code'].' '.$x['name'],
                $this->peso($x['budget']),
                $this->peso($x['actual']),
                $this->peso($x['variance']),
                $x['pct'] !== null ? $x['pct'].'%' : '—',
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'totals' => ['TOTAL', $this->peso($r['total_budget']), $this->peso($r['total_actual']), $this->peso($r['total_variance']), ''],
        ];
    }
}
