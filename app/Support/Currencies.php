<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Supported currency codes. The functional (book) currency is PHP; the rest are
 * transaction currencies that convert to PHP via an ExchangeRate. (§17)
 */
final class Currencies
{
    public const FUNCTIONAL = 'PHP';

    public const SUPPORTED = ['PHP', 'USD', 'EUR', 'JPY', 'GBP', 'SGD', 'AUD', 'CNY', 'HKD', 'CAD'];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(self::SUPPORTED, self::SUPPORTED);
    }

    /**
     * @return array<string, string>
     */
    public static function foreignOptions(): array
    {
        $foreign = array_values(array_filter(self::SUPPORTED, fn (string $c): bool => $c !== self::FUNCTIONAL));

        return array_combine($foreign, $foreign);
    }
}
