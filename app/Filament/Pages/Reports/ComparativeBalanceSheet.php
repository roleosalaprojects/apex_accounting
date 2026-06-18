<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\BalanceSheetReport;
use Illuminate\Support\Carbon;

class ComparativeBalanceSheet extends ReportPage
{
    protected static ?string $navigationLabel = 'Balance Sheet (Comparative)';

    protected static ?int $navigationSort = 1;

    public function getTitle(): string
    {
        return 'Comparative Balance Sheet (year on year)';
    }

    protected function usesRange(): bool
    {
        return false;
    }

    protected function payload(): array
    {
        $svc = app(BalanceSheetReport::class);
        $company = $this->company();

        $cur = $svc->build($company, (string) $this->asOf);
        $prior = $svc->build($company, Carbon::parse((string) $this->asOf)->subYear()->toDateString());

        $rows = [];
        foreach (['assets' => 'ASSETS', 'liabilities' => 'LIABILITIES', 'equity' => 'EQUITY'] as $key => $label) {
            $rows[] = [$label, '', '', ''];
            foreach ($this->merge($cur[$key], $prior[$key]) as $r) {
                $rows[] = [$r['name'], $this->peso($r['cur']), $this->peso($r['prior']), $this->peso($r['cur'] - $r['prior'])];
            }
        }
        $rows[] = ['Total assets', $this->peso($cur['total_assets']), $this->peso($prior['total_assets']), $this->peso($cur['total_assets'] - $prior['total_assets'])];

        $curLiabEq = $cur['total_liabilities'] + $cur['total_equity'];
        $priorLiabEq = $prior['total_liabilities'] + $prior['total_equity'];

        return [
            'columns' => ['Account', 'Current', 'Prior year', 'Change'],
            'rows' => $rows,
            'totals' => ['TOTAL LIABILITIES + EQUITY', $this->peso($curLiabEq), $this->peso($priorLiabEq), $this->peso($curLiabEq - $priorLiabEq)],
        ];
    }

    /**
     * Union two as-of line sets, keyed by account code (falling back to name for
     * the synthetic current-year-earnings line).
     *
     * @param  array<int, array<string, mixed>>  $cur
     * @param  array<int, array<string, mixed>>  $prior
     * @return array<string, array{name: string, cur: int, prior: int}>
     */
    private function merge(array $cur, array $prior): array
    {
        $label = fn (array $l): string => ($l['code'] !== null ? (string) $l['code'].' ' : '').(string) $l['name'];
        $key = fn (array $l): string => (string) ($l['code'] ?? $l['name']);

        $map = [];
        foreach ($cur as $l) {
            $map[$key($l)] = ['name' => $label($l), 'cur' => (int) $l['amount'], 'prior' => 0];
        }
        foreach ($prior as $l) {
            $map[$key($l)] ??= ['name' => $label($l), 'cur' => 0, 'prior' => 0];
            $map[$key($l)]['prior'] = (int) $l['amount'];
        }
        ksort($map);

        return $map;
    }
}
