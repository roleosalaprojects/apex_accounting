<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\JournalStatus;
use App\Enums\PeriodStatus;
use App\Enums\PosZReadingStatus;
use App\Filament\Pages\Reports\Dunning;
use App\Filament\Resources\AccountingPeriods\AccountingPeriodResource;
use App\Filament\Resources\BankStatementLines\BankStatementLineResource;
use App\Filament\Resources\Bills\BillResource;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Resources\PosZReadings\PosZReadingResource;
use App\Filament\Resources\RecurringTemplates\RecurringTemplateResource;
use App\Filament\Widgets\Concerns\ChecksCompanyPermission;
use App\Models\AccountingPeriod;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PosZReading;
use App\Models\RecurringRun;
use App\Services\Reports\ApAgingReport;
use App\Services\Reports\ArAgingReport;
use App\Support\Rbac\RbacRegistry;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

/**
 * The bookkeeping to-do list. Each item shows only to members who can act on
 * it, and only when there is something to do.
 */
class NeedsAttention extends Widget
{
    use ChecksCompanyPermission;

    protected static ?int $sort = 6;

    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        return static::company() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Company $company */
        $company = static::company();
        $today = CarbonImmutable::today();
        $items = [];

        if (static::userMay(RbacRegistry::JOURNAL_APPROVE)) {
            $items[] = $this->item('heroicon-o-document-check', 'Journal entries to approve', 'Drafts from imports and recurring runs',
                JournalEntry::query()->where('status', JournalStatus::Draft->value)->count(), JournalEntryResource::getUrl('index'));
        }

        if (static::userMay(RbacRegistry::JOURNAL_CREATE)) {
            $items[] = $this->item('heroicon-o-inbox-arrow-down', 'POS Z-readings to import', 'Waiting in the integration inbox',
                PosZReading::query()->where('status', PosZReadingStatus::Pending->value)->count(), PosZReadingResource::getUrl('index'));
        }

        if (static::userMay(RbacRegistry::BANK_RECONCILE)) {
            $items[] = $this->item('heroicon-o-banknotes', 'Bank lines to match', 'Imported statement lines not yet in the ledger',
                BankStatementLine::query()->where('status', 'unmatched')->count(), BankStatementLineResource::getUrl('index'));
        }

        if (static::userMay(RbacRegistry::REPORTS_VIEW)) {
            $overdue = array_filter(app(ArAgingReport::class)->build($company->id, $today->toDateString())['rows'],
                fn (array $row): bool => $row['bucket'] !== 'current');
            $items[] = $this->item('heroicon-o-envelope', 'Invoices to follow up', 'Past due — see the dunning list',
                count($overdue), Dunning::getUrl());
        }

        if (BillResource::canViewAny()) {
            $late = array_filter(app(ApAgingReport::class)->build($company->id, $today->toDateString())['rows'],
                fn (array $row): bool => $row['due_date'] < $today->toDateString());
            $items[] = $this->item('heroicon-o-exclamation-circle', 'Bills past due', 'Unpaid after their due date',
                count($late), BillResource::getUrl('index'));
        }

        if (static::userMay(RbacRegistry::PERIOD_CLOSE)) {
            $items[] = $this->item('heroicon-o-lock-open', 'Past months still open', 'Close them to lock the books',
                AccountingPeriod::query()->where('status', PeriodStatus::Open->value)->whereDate('ends_on', '<', $today->startOfMonth()->toDateString())->count(),
                AccountingPeriodResource::getUrl('index'));
        }

        if (static::userMay(RbacRegistry::RECURRING_MANAGE)) {
            $items[] = $this->item('heroicon-o-arrow-path', 'Recurring runs that failed', 'In the last 30 days',
                RecurringRun::query()->where('status', 'failed')->whereDate('ran_on', '>=', $today->subDays(30)->toDateString())->count(),
                RecurringTemplateResource::getUrl('index'));
        }

        return ['items' => array_values(array_filter($items, fn (array $item): bool => $item['count'] > 0))];
    }

    /**
     * @return array{icon: string, label: string, detail: string, count: int, url: string}
     */
    private function item(string $icon, string $label, string $detail, int $count, string $url): array
    {
        return ['icon' => $icon, 'label' => $label, 'detail' => $detail, 'count' => $count, 'url' => $url];
    }
}
