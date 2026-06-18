<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Services\Reports\ProfitAndLossReport;
use Illuminate\Support\Carbon;

class ComparativeProfitAndLoss extends ReportPage
{
    protected static ?string $navigationLabel = 'P&L (Comparative)';

    protected static ?int $navigationSort = 3;

    public function getTitle(): string
    {
        return 'Comparative Profit & Loss (year on year)';
    }

    protected function payload(): array
    {
        $svc = app(ProfitAndLossReport::class);
        $companyId = $this->company()->id;

        $cur = $svc->build($companyId, (string) $this->from, (string) $this->asOf);
        $prior = $svc->build(
            $companyId,
            Carbon::parse((string) $this->from)->subYear()->toDateString(),
            Carbon::parse((string) $this->asOf)->subYear()->toDateString(),
        );

        $rows = [['INCOME', '', '', '']];
        foreach ($this->merge($cur['income'], $prior['income']) as $r) {
            $rows[] = [$r['name'], $this->peso($r['cur']), $this->peso($r['prior']), $this->peso($r['cur'] - $r['prior'])];
        }
        $rows[] = ['Total income', $this->peso($cur['total_income']), $this->peso($prior['total_income']), $this->peso($cur['total_income'] - $prior['total_income'])];
        $rows[] = ['EXPENSES', '', '', ''];
        foreach ($this->merge($cur['expense'], $prior['expense']) as $r) {
            $rows[] = [$r['name'], $this->peso($r['cur']), $this->peso($r['prior']), $this->peso($r['cur'] - $r['prior'])];
        }
        $rows[] = ['Total expenses', $this->peso($cur['total_expense']), $this->peso($prior['total_expense']), $this->peso($cur['total_expense'] - $prior['total_expense'])];

        return [
            'columns' => ['Account', 'Current', 'Prior year', 'Change'],
            'rows' => $rows,
            'totals' => ['NET INCOME', $this->peso($cur['net_income']), $this->peso($prior['net_income']), $this->peso($cur['net_income'] - $prior['net_income'])],
        ];
    }

    /**
     * Union two period line sets, keyed by account code.
     *
     * @param  array<int, array<string, mixed>>  $cur
     * @param  array<int, array<string, mixed>>  $prior
     * @return array<string, array{name: string, cur: int, prior: int}>
     */
    private function merge(array $cur, array $prior): array
    {
        $map = [];
        foreach ($cur as $l) {
            $map[(string) $l['code']] = ['name' => (string) $l['code'].' '.(string) $l['name'], 'cur' => (int) $l['amount'], 'prior' => 0];
        }
        foreach ($prior as $l) {
            $map[(string) $l['code']] ??= ['name' => (string) $l['code'].' '.(string) $l['name'], 'cur' => 0, 'prior' => 0];
            $map[(string) $l['code']]['prior'] = (int) $l['amount'];
        }
        ksort($map);

        return $map;
    }
}
