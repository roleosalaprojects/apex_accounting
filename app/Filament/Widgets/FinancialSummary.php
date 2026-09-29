<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AccountSubtype;
use App\Filament\Support\Peso;
use App\Filament\Widgets\Concerns\ChecksCompanyPermission;
use App\Models\Company;
use App\Services\Reports\ApAgingReport;
use App\Services\Reports\ArAgingReport;
use App\Services\Reports\DashboardMetrics;
use App\Services\Reports\ProfitAndLossReport;
use App\Support\Rbac\RbacRegistry;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * The dashboard's key figures: year-to-date results against the same period
 * last year, and today's cash, receivables and payables. Each tile carries a
 * 12-month trend.
 */
class FinancialSummary extends Widget
{
    use ChecksCompanyPermission;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.financial-summary';

    public static function canView(): bool
    {
        return static::userMay(RbacRegistry::REPORTS_VIEW);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Company $company */
        $company = static::company();
        $today = CarbonImmutable::today();
        $metrics = app(DashboardMetrics::class);

        $yearStart = CarbonImmutable::create($today->year, $company->fiscal_year_start_month, 1);
        if ($yearStart->greaterThan($today)) {
            $yearStart = $yearStart->subYear();
        }

        $pnl = app(ProfitAndLossReport::class);
        $current = $pnl->build($company->id, $yearStart->toDateString(), $today->toDateString());
        $prior = $pnl->build($company->id, $yearStart->subYear()->toDateString(), $today->subYear()->toDateString());
        $trend = $metrics->monthlyProfitAndLoss($company->id, $today);
        $priorLabel = 'vs '.$yearStart->subYear()->format('Y').' same period';

        $cash = $metrics->monthlyBalances($company->id, [AccountSubtype::Cash, AccountSubtype::Bank], $today);
        $receivables = $metrics->monthlyBalances($company->id, [AccountSubtype::AccountsReceivable], $today);
        $payables = $metrics->monthlyBalances($company->id, [AccountSubtype::AccountsPayable], $today);

        $cashNow = end($cash)['balance'];
        $cashLastMonth = $cash[count($cash) - 2]['balance'];

        return [
            'groups' => [
                [
                    'title' => 'Year to date',
                    'caption' => $yearStart->format('M j').' – '.$today->format('M j, Y'),
                    'tiles' => [
                        $this->tile('Income', $current['total_income'], array_column($trend, 'income'),
                            $this->delta($current['total_income'], $prior['total_income'], $priorLabel, upIsGood: true)),
                        $this->tile('Expenses', $current['total_expense'], array_column($trend, 'expenses'),
                            $this->delta($current['total_expense'], $prior['total_expense'], $priorLabel, upIsGood: false),
                            'Cost of sales and operating expenses'),
                        $this->tile('Net income', $current['net_income'], array_column($trend, 'net'),
                            $this->delta($current['net_income'], $prior['net_income'], $priorLabel, upIsGood: true),
                            $current['total_income'] > 0 ? number_format($current['net_income'] / $current['total_income'] * 100, 1).'% net margin' : null),
                    ],
                ],
                [
                    'title' => 'Position today',
                    'caption' => $today->format('l, M j'),
                    'tiles' => [
                        $this->tile('Cash and bank', $cashNow, array_column($cash, 'balance'),
                            $this->change($cashNow - $cashLastMonth, 'since '.$today->startOfMonth()->subDay()->format('M j'))),
                        $this->tile('Receivables', end($receivables)['balance'], array_column($receivables, 'balance'),
                            $this->overdueReceivables($company->id, $today)),
                        $this->tile('Payables', -end($payables)['balance'], array_map(fn (int $b): int => -$b, array_column($payables, 'balance')),
                            ...$this->payablesNotes($company->id, $today)),
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<int>  $trend
     * @param  array{text: string, tone: string, icon: string}|null  $note
     * @return array<string, mixed>
     */
    private function tile(string $label, int $minor, array $trend, ?array $note = null, ?string $sub = null): array
    {
        return [
            'label' => $label,
            'value' => Peso::compact($minor),
            'exact' => Peso::format($minor),
            'note' => $note,
            'sub' => $sub,
            'spark' => $this->sparkline($trend),
        ];
    }

    /**
     * @return array{text: string, tone: string, icon: string}|null
     */
    private function delta(int $current, int $prior, string $versus, bool $upIsGood): ?array
    {
        if ($prior === 0) {
            return null;
        }

        $pct = ($current - $prior) / abs($prior) * 100;
        $up = $pct >= 0;

        return [
            'text' => ($up ? '+' : '−').number_format(abs($pct), 1).'% '.$versus,
            'tone' => $up === $upIsGood ? 'success' : 'danger',
            'icon' => $up ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
        ];
    }

    /**
     * @return array{text: string, tone: string, icon: string}
     */
    private function change(int $minor, string $since): array
    {
        return [
            'text' => ($minor >= 0 ? '+' : '').Peso::compact($minor).' '.$since,
            'tone' => $minor >= 0 ? 'success' : 'danger',
            'icon' => $minor >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
        ];
    }

    /**
     * @return array{text: string, tone: string, icon: string}
     */
    private function overdueReceivables(int $companyId, CarbonImmutable $today): array
    {
        $aging = app(ArAgingReport::class)->build($companyId, $today->toDateString());
        $overdue = array_filter($aging['rows'], fn (array $row): bool => $row['bucket'] !== 'current');

        if ($overdue === []) {
            return ['text' => 'Nothing overdue', 'tone' => 'success', 'icon' => 'heroicon-m-check-circle'];
        }

        $amount = array_sum(array_column($overdue, 'outstanding'));

        return [
            'text' => Peso::compact($amount).' overdue · '.count($overdue).' '.str('invoice')->plural(count($overdue)),
            'tone' => 'warning',
            'icon' => 'heroicon-m-exclamation-triangle',
        ];
    }

    /**
     * @return array{0: array{text: string, tone: string, icon: string}, 1: string|null}
     */
    private function payablesNotes(int $companyId, CarbonImmutable $today): array
    {
        $rows = app(ApAgingReport::class)->build($companyId, $today->toDateString())['rows'];
        $weekAhead = $today->addDays(7)->toDateString();

        $overdue = array_sum(array_column(array_filter($rows, fn (array $r): bool => $r['due_date'] < $today->toDateString()), 'outstanding'));
        $dueSoon = array_sum(array_column(array_filter($rows, fn (array $r): bool => $r['due_date'] >= $today->toDateString() && $r['due_date'] <= $weekAhead), 'outstanding'));

        $soon = ['text' => Peso::compact($dueSoon).' due in the next 7 days', 'tone' => 'gray', 'icon' => 'heroicon-m-calendar-days'];

        return $overdue > 0
            ? [['text' => Peso::compact($overdue).' overdue', 'tone' => 'danger', 'icon' => 'heroicon-m-exclamation-circle'], $soon['text']]
            : [$soon, null];
    }

    /**
     * Polyline points for a trend in a 100 × 100 box that the view stretches to
     * the tile's width, plus the last point's height (%) for the end marker.
     *
     * @param  list<int>  $values
     * @return array{points: string, lastY: float}|null
     */
    private function sparkline(array $values): ?array
    {
        if (count($values) < 2) {
            return null;
        }

        [$min, $max] = [min($values), max($values)];
        $range = $max - $min ?: 1;
        $step = 100 / (count($values) - 1);

        $points = [];
        foreach (array_values($values) as $i => $value) {
            $points[] = ['x' => round($i * $step, 2), 'y' => round(10 + (1 - ($value - $min) / $range) * 80, 2)];
        }

        return [
            'points' => implode(' ', array_map(fn (array $p): string => "{$p['x']},{$p['y']}", $points)),
            'lastY' => end($points)['y'],
        ];
    }
}
