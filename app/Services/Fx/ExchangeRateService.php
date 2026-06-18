<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Models\ExchangeRate;
use App\Support\Currencies;
use RuntimeException;

/**
 * Resolves the functional-currency conversion rate for a transaction and
 * converts foreign minor amounts to functional (PHP) minor. (§17)
 */
final class ExchangeRateService
{
    /**
     * The latest rate on or before $date for $currency. The functional currency
     * is always 1.0.
     */
    public function rateFor(int $companyId, string $currency, string $date): float
    {
        if ($currency === Currencies::FUNCTIONAL) {
            return 1.0;
        }

        $rate = ExchangeRate::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('currency_code', $currency)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->value('rate');

        if ($rate === null) {
            throw new RuntimeException("No exchange rate for {$currency} on or before {$date}.");
        }

        return (float) $rate;
    }

    /** Convert a foreign-currency minor amount to functional (PHP) minor. */
    public function toFunctional(int $minorForeign, float $rate): int
    {
        return (int) round($minorForeign * $rate);
    }
}
