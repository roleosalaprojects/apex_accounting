<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Peso amounts spelled out as on checks, receipts and vouchers:
 * "FIFTY-THREE THOUSAND FIVE HUNDRED PESOS AND 00/100".
 */
final class AmountInWords
{
    private const ONES = ['', 'ONE', 'TWO', 'THREE', 'FOUR', 'FIVE', 'SIX', 'SEVEN', 'EIGHT', 'NINE', 'TEN', 'ELEVEN', 'TWELVE',
        'THIRTEEN', 'FOURTEEN', 'FIFTEEN', 'SIXTEEN', 'SEVENTEEN', 'EIGHTEEN', 'NINETEEN'];

    private const TENS = ['', '', 'TWENTY', 'THIRTY', 'FORTY', 'FIFTY', 'SIXTY', 'SEVENTY', 'EIGHTY', 'NINETY'];

    private const SCALES = ['', ' THOUSAND', ' MILLION', ' BILLION', ' TRILLION'];

    public static function pesos(int $minor): string
    {
        $sign = $minor < 0 ? 'MINUS ' : '';
        $minor = abs($minor);
        $pesos = intdiv($minor, 100);
        $centavos = $minor % 100;

        $words = $pesos === 0 ? 'ZERO' : self::spell($pesos);

        return sprintf('%s%s %s AND %02d/100', $sign, $words, $pesos === 1 ? 'PESO' : 'PESOS', $centavos);
    }

    private static function spell(int $number): string
    {
        $groups = [];
        for ($scale = 0; $number > 0; $scale++, $number = intdiv($number, 1000)) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                array_unshift($groups, self::hundreds($chunk).self::SCALES[$scale]);
            }
        }

        return implode(' ', $groups);
    }

    private static function hundreds(int $chunk): string
    {
        $parts = [];
        if ($chunk >= 100) {
            $parts[] = self::ONES[intdiv($chunk, 100)].' HUNDRED';
            $chunk %= 100;
        }
        if ($chunk >= 20) {
            $parts[] = self::TENS[intdiv($chunk, 10)].($chunk % 10 > 0 ? '-'.self::ONES[$chunk % 10] : '');
        } elseif ($chunk > 0) {
            $parts[] = self::ONES[$chunk];
        }

        return implode(' ', $parts);
    }
}
