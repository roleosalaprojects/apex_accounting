<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\Reports\ApAging;
use App\Filament\Pages\Reports\ArAging;
use App\Filament\Support\Peso;
use App\Filament\Widgets\Concerns\ChecksCompanyPermission;
use App\Models\Company;
use App\Services\Reports\ApAgingReport;
use App\Services\Reports\ArAgingReport;
use App\Support\Rbac\RbacRegistry;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * Who owes the company and whom it owes, today: receivables by days past due
 * with the largest overdue customers, and payables by when they fall due with
 * the next bills to pay.
 */
class ReceivablesAndPayables extends Widget
{
    use ChecksCompanyPermission;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = ['md' => 2];

    protected string $view = 'filament.widgets.receivables-and-payables';

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

        return [
            'receivables' => $this->receivables($company->id, $today),
            'payables' => $this->payables($company->id, $today),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function receivables(int $companyId, CarbonImmutable $today): array
    {
        $aging = app(ArAgingReport::class)->build($companyId, $today->toDateString());
        $labels = ['current' => 'Not yet due', '1_30' => '1–30 days late', '31_60' => '31–60 days late', '61_90' => '61–90 days late', '90_plus' => 'Over 90 days late'];

        $overdueByCustomer = [];
        foreach ($aging['rows'] as $row) {
            if ($row['bucket'] === 'current') {
                continue;
            }
            $name = (string) $row['customer'];
            $overdueByCustomer[$name]['amount'] = ($overdueByCustomer[$name]['amount'] ?? 0) + $row['outstanding'];
            $overdueByCustomer[$name]['oldest'] = min($overdueByCustomer[$name]['oldest'] ?? $row['due_date'], $row['due_date']);
        }
        uasort($overdueByCustomer, fn (array $a, array $b): int => $b['amount'] <=> $a['amount']);

        return [
            'title' => 'Owed to you',
            'total' => Peso::compact($aging['total']),
            'exact' => Peso::format($aging['total']),
            'rows' => $this->shares($aging['buckets'], $labels, $aging['total']),
            'listTitle' => 'Largest overdue',
            'list' => array_map(fn (string $name, array $c): array => [
                'name' => $name,
                'amount' => Peso::compact($c['amount']),
                'detail' => (int) CarbonImmutable::parse($c['oldest'])->diffInDays($today, true).' days late',
            ], array_keys(array_slice($overdueByCustomer, 0, 3, true)), array_slice($overdueByCustomer, 0, 3, true)),
            'empty' => 'No overdue invoices.',
            'link' => ArAging::getUrl(),
            'linkLabel' => 'Receivables aging',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payables(int $companyId, CarbonImmutable $today): array
    {
        $aging = app(ApAgingReport::class)->build($companyId, $today->toDateString());
        $week = $today->addDays(7)->toDateString();
        $month = $today->addDays(30)->toDateString();

        $buckets = ['overdue' => 0, 'week' => 0, 'month' => 0, 'later' => 0];
        foreach ($aging['rows'] as $row) {
            $bucket = match (true) {
                $row['due_date'] < $today->toDateString() => 'overdue',
                $row['due_date'] <= $week => 'week',
                $row['due_date'] <= $month => 'month',
                default => 'later',
            };
            $buckets[$bucket] += $row['outstanding'];
        }

        $next = $aging['rows'];
        usort($next, fn (array $a, array $b): int => [$a['due_date'], -$a['outstanding']] <=> [$b['due_date'], -$b['outstanding']]);

        return [
            'title' => 'You owe',
            'total' => Peso::compact($aging['total']),
            'exact' => Peso::format($aging['total']),
            'rows' => $this->shares($buckets, ['overdue' => 'Overdue', 'week' => 'Due in 7 days', 'month' => 'Due in 8–30 days', 'later' => 'Due later'], $aging['total']),
            'listTitle' => 'Next to pay',
            'list' => array_map(fn (array $bill): array => [
                'name' => (string) $bill['vendor'],
                'amount' => Peso::compact($bill['outstanding']),
                'detail' => ($bill['due_date'] < $today->toDateString() ? 'was due ' : 'due ').CarbonImmutable::parse($bill['due_date'])->format('M j'),
            ], array_slice($next, 0, 3)),
            'empty' => 'No open bills.',
            'link' => ApAging::getUrl(),
            'linkLabel' => 'Payables aging',
        ];
    }

    /**
     * @param  array<string, int>  $amounts
     * @param  array<string, string>  $labels
     * @return list<array{label: string, amount: string, exact: string, share: float}>
     */
    private function shares(array $amounts, array $labels, int $total): array
    {
        $rows = [];
        foreach ($labels as $key => $label) {
            $amount = $amounts[$key] ?? 0;
            $rows[] = [
                'label' => $label,
                'amount' => Peso::compact($amount),
                'exact' => Peso::format($amount),
                'share' => $total > 0 ? round($amount / $total * 100, 1) : 0.0,
            ];
        }

        return $rows;
    }
}
