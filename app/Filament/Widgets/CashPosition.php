<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AccountSubtype;
use App\Filament\Pages\Reports\GeneralLedger;
use App\Filament\Widgets\Concerns\ChecksCompanyPermission;
use App\Models\Company;
use App\Services\Reports\DashboardMetrics;
use App\Support\Rbac\RbacRegistry;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/** Cash and bank balance at each month-end (the last point is today). */
class CashPosition extends ChartWidget
{
    use ChecksCompanyPermission;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Cash position';

    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return static::userMay(RbacRegistry::REPORTS_VIEW);
    }

    public function getDescription(): Htmlable
    {
        return new HtmlString(e('Cash and bank at each month-end. ')
            .'<a href="'.e(GeneralLedger::getUrl()).'" class="fi-link font-medium underline underline-offset-2">Open the ledger</a>');
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        /** @var Company $company */
        $company = static::company();
        $series = app(DashboardMetrics::class)->monthlyBalances(
            $company->id,
            [AccountSubtype::Cash, AccountSubtype::Bank],
            CarbonImmutable::today(),
        );

        return [
            'labels' => array_map(fn (array $p): string => $p['month']->format('M y'), $series),
            'datasets' => [
                ['label' => 'Cash and bank', 'data' => array_map(fn (array $p): float => $p['balance'] / 100, $series), 'fill' => 'origin'],
            ],
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                aspectRatio: 1.3,
                interaction: { mode: 'index', intersect: false },
                datasets: {
                    line: {
                        borderColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-series-1').trim(),
                        backgroundColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-series-1-wash').trim(),
                        pointBackgroundColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-series-1').trim(),
                        pointBorderColor: (ctx) => getComputedStyle(ctx.chart.canvas).getPropertyValue('--viz-surface').trim(),
                        borderWidth: 2,
                        pointBorderWidth: 2,
                        pointRadius: (ctx) => ctx.dataIndex === ctx.dataset.data.length - 1 ? 4 : 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12,
                        tension: 0,
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 }).format(ctx.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: { ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            maxTicksLimit: 5,
                            callback: (value) => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', notation: 'compact', maximumFractionDigits: 1 }).format(value),
                        },
                    },
                },
            }
        JS);
    }
}
