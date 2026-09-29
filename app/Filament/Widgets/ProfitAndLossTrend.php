<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Reports\ComparativeProfitAndLoss;
use App\Filament\Widgets\Concerns\ChecksCompanyPermission;
use App\Models\Company;
use App\Services\Reports\DashboardMetrics;
use App\Support\Rbac\RbacRegistry;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Monthly income and expenses as paired columns with net income as a line —
 * one peso axis. Colors come from the --viz-* tokens in the panel theme, so
 * they follow light and dark mode.
 */
class ProfitAndLossTrend extends ChartWidget
{
    use ChecksCompanyPermission;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['md' => 2];

    protected ?string $heading = 'Income and expenses';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return static::userMay(RbacRegistry::REPORTS_VIEW);
    }

    public function getDescription(): Htmlable
    {
        return new HtmlString(e('Last 12 months, with net income as a line. ')
            .'<a href="'.e(ComparativeProfitAndLoss::getUrl()).'" class="fi-link font-medium underline underline-offset-2">See the figures</a>');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var Company $company */
        $company = static::company();
        $series = app(DashboardMetrics::class)->monthlyProfitAndLoss($company->id, CarbonImmutable::today());

        return [
            'labels' => array_map(fn (array $p): string => $p['month']->format('M y'), $series),
            'datasets' => [
                ['label' => 'Income', 'data' => array_map(fn (array $p): float => $p['income'] / 100, $series), 'order' => 2],
                ['label' => 'Expenses', 'data' => array_map(fn (array $p): float => $p['expenses'] / 100, $series), 'order' => 2],
                ['type' => 'line', 'label' => 'Net income', 'data' => array_map(fn (array $p): float => $p['net'] / 100, $series), 'order' => 1],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                interaction: { mode: 'index', intersect: false },
                datasets: {
                    bar: {
                        backgroundColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue(ctx.datasetIndex === 0 ? '--viz-series-1' : '--viz-series-2').trim(),
                        hoverBackgroundColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue(ctx.datasetIndex === 0 ? '--viz-series-1' : '--viz-series-2').trim(),
                        borderWidth: 0,
                        borderRadius: 4,
                        borderSkipped: 'start',
                        maxBarThickness: 24,
                        categoryPercentage: 0.72,
                        barPercentage: 0.9,
                    },
                    line: {
                        borderColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-series-3').trim(),
                        backgroundColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-series-3').trim(),
                        pointBackgroundColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-series-3').trim(),
                        pointBorderColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-surface').trim(),
                        borderWidth: 2,
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 5,
                        pointHitRadius: 12,
                        tension: 0,
                    },
                },
                plugins: {
                    legend: { labels: { usePointStyle: true, pointStyle: 'rectRounded', sort: (a, b) => a.datasetIndex - b.datasetIndex } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.dataset.label}: ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(ctx.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: { ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                    y: {
                        ticks: {
                            callback: (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 }).format(value),
                        },
                    },
                },
            }
        JS);
    }
}
