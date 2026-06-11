<?php

declare(strict_types=1);

namespace App\Services\Assets;

/**
 * Straight-line depreciation (§10): monthly = (cost − salvage) ÷ life, integer
 * division; the final month absorbs the remainder so the schedule sums exactly
 * to the depreciable base.
 */
final class DepreciationSchedule
{
    /**
     * Amount for the Nth entry (1-indexed). 0 once fully depreciated.
     */
    public function amountForEntry(int $depreciable, int $lifeMonths, int $entryNo): int
    {
        if ($lifeMonths <= 0 || $entryNo < 1 || $entryNo > $lifeMonths) {
            return 0;
        }

        $monthly = intdiv($depreciable, $lifeMonths);

        if ($entryNo === $lifeMonths) {
            return $depreciable - ($monthly * ($lifeMonths - 1));
        }

        return $monthly;
    }

    /**
     * Full schedule of per-period amounts (length = life).
     *
     * @return array<int, int>
     */
    public function fullSchedule(int $depreciable, int $lifeMonths): array
    {
        $schedule = [];
        for ($n = 1; $n <= $lifeMonths; $n++) {
            $schedule[] = $this->amountForEntry($depreciable, $lifeMonths, $n);
        }

        return $schedule;
    }
}
