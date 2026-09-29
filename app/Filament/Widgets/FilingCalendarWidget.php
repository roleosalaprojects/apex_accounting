<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\TaxpayerType;
use App\Filament\Resources\TaxReturns\TaxReturnResource;
use App\Filament\Support\Peso;
use App\Filament\Widgets\Concerns\ChecksCompanyPermission;
use App\Models\Company;
use App\Services\Reports\DashboardMetrics;
use App\Services\Tax\FilingCalendar;
use App\Support\Rbac\RbacRegistry;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * The next BIR deadlines, each with what the ledger currently shows as due:
 * net VAT (output less input), EWT payable and compensation withholding
 * payable. Income tax dates assume a corporation (1702Q).
 */
class FilingCalendarWidget extends Widget
{
    use ChecksCompanyPermission;

    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.filing-calendar';

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
        $balance = fn (string $code): int => $metrics->creditBalanceOfCode($company->id, $code, $today->toDateString());

        $netVat = $balance('2200') + $balance('1400');
        $amounts = [
            'business' => $company->taxpayer_type === TaxpayerType::Vat
                ? ($netVat >= 0 ? ['Net VAT so far', $netVat] : ['Excess input VAT', -$netVat])
                : null,
            'ewt' => ['EWT payable', $balance('2210')],
            'compensation' => ['Withholding payable', $balance('2220')],
            'income' => null,
        ];

        $deadlines = [];
        foreach (app(FilingCalendar::class)->upcoming($today, $company->taxpayer_type, $company->fiscal_year_start_month) as $deadline) {
            $days = (int) $today->diffInDays($deadline['due']);
            $amount = $amounts[$deadline['kind']];

            $deadlines[] = [
                'form' => $deadline['form'],
                'title' => $deadline['title'],
                'period' => $deadline['period'],
                'due' => $deadline['due']->format('D, M j'),
                'when' => match (true) {
                    $days === 0 => 'Due today',
                    $days === 1 => 'Due tomorrow',
                    default => "In {$days} days",
                },
                'soon' => $days <= 7,
                'amountLabel' => $amount[0] ?? null,
                'amount' => $amount !== null ? Peso::format($amount[1]) : null,
            ];
        }

        return [
            'deadlines' => $deadlines,
            'returnsUrl' => TaxReturnResource::canViewAny() ? TaxReturnResource::getUrl('index') : null,
        ];
    }
}
