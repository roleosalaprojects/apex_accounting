<?php

declare(strict_types=1);

namespace App\Services\Assets;

use App\Models\Asset;
use Illuminate\Support\Carbon;

/**
 * Where an asset stands: months depreciated, accumulated depreciation, net
 * book value, and — for the straight-line schedule — when it will be fully
 * depreciated and what the remaining months look like.
 */
final class AssetFigures
{
    public function __construct(private readonly DepreciationSchedule $schedule) {}

    /**
     * @return array{months_done: int, months_left: int, accumulated: int, net_book_value: int, monthly: int, last_month: string|null, schedule: list<array{month: string, amount: int, accumulated: int, net_book_value: int, posted: bool}>}
     */
    public function build(Asset $asset): array
    {
        $entries = $asset->depreciationEntries()->with('period')->get()
            ->sortBy(fn ($entry): string => $entry->period?->starts_on?->toDateString() ?? '')->values();
        $done = $entries->count();
        $life = $asset->useful_life_months;
        $accumulated = (int) $entries->sum(fn ($entry): int => $entry->amount->minor);
        $cost = $asset->acquisition_cost->minor;

        $start = $asset->in_service_date ?? $asset->acquisition_date;
        $rows = [];
        $running = 0;
        for ($n = 1; $n <= $life; $n++) {
            $posted = $n <= $done;
            $amount = $posted ? $entries[$n - 1]->amount->minor : $this->schedule->amountForEntry($asset->depreciableBase(), $life, $n);
            $running += $amount;
            $month = $posted && $entries[$n - 1]->period !== null
                ? Carbon::parse($entries[$n - 1]->period->starts_on)
                : $start->copy()->startOfMonth()->addMonthsNoOverflow($n - 1);
            $rows[] = [
                'month' => $month->format('M Y'),
                'amount' => $amount,
                'accumulated' => $running,
                'net_book_value' => $cost - $running,
                'posted' => $posted,
            ];
        }

        return [
            'months_done' => $done,
            'months_left' => max(0, $life - $done),
            'accumulated' => $accumulated,
            'net_book_value' => $asset->status->value === 'disposed' ? 0 : $cost - $accumulated,
            'monthly' => $this->schedule->amountForEntry($asset->depreciableBase(), $life, 1),
            'last_month' => $rows === [] ? null : end($rows)['month'],
            'schedule' => $rows,
        ];
    }
}
