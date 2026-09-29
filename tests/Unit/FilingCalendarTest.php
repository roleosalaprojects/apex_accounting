<?php

declare(strict_types=1);

use App\Enums\TaxpayerType;
use App\Services\Tax\FilingCalendar;
use Carbon\CarbonImmutable;

/**
 * @return array<string, string> form => due date
 */
function deadlines(string $today, TaxpayerType $type = TaxpayerType::Vat, int $fiscalStartMonth = 1): array
{
    $out = [];
    foreach ((new FilingCalendar)->upcoming(CarbonImmutable::parse($today), $type, $fiscalStartMonth) as $deadline) {
        $out[$deadline['form'].' '.$deadline['period']] = $deadline['due']->toDateString();
    }

    return $out;
}

it('lists the next deadline of each return, soonest first', function () {
    expect(deadlines('2026-09-29'))->toBe([
        '1601-C September 2026' => '2026-10-10',
        '2550Q Q3 2026' => '2026-10-25',
        '1601-EQ Q3 2026' => '2026-10-31',
        '1702Q Q3 2026' => '2026-11-29',
    ]);
});

it('switches to monthly 0619-E mid-quarter and to the next quarter once a deadline passes', function () {
    expect(deadlines('2026-11-02'))->toBe([
        '0619-E October 2026' => '2026-11-10',
        '1601-C October 2026' => '2026-11-10',
        '1702Q Q3 2026' => '2026-11-29',
        '2550Q Q4 2026' => '2027-01-25',
    ]);
});

it('gives December compensation withholding until January 15 and the annual return after Q4', function () {
    expect(deadlines('2027-01-02'))->toBe([
        '1601-C December 2026' => '2027-01-15',
        '2550Q Q4 2026' => '2027-01-25',
        '1601-EQ Q4 2026' => '2027-01-31',
        '1702-RT FY 2026' => '2027-04-15',
    ]);
});

it('uses percentage tax for non-VAT taxpayers', function () {
    expect(deadlines('2026-09-29', TaxpayerType::NonVat))->toHaveKey('2551Q Q3 2026', '2026-10-25');
});

it('follows fiscal quarters for business and income tax, calendar quarters for withholding', function () {
    // Fiscal year July–June: Jul–Sep is Q1 of FY 2026, and FY 2025 closed in June 2026.
    expect(deadlines('2026-09-29', fiscalStartMonth: 7))->toBe([
        '1601-C September 2026' => '2026-10-10',
        '1702-RT FY 2025' => '2026-10-15',
        '2550Q Q1 2026' => '2026-10-25',
        '1601-EQ Q3 2026' => '2026-10-31',
    ]);
});
