<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Enums\TaxpayerType;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * The next BIR deadline for each return the ledger feeds, on the manual-filing
 * schedule: business tax (2550Q, or 2551Q when non-VAT) 25 days after the
 * quarter; expanded withholding (0619-E on the 10th, 1601-EQ at the end of the
 * month after the quarter); compensation withholding (1601-C on the 10th,
 * January 15 for December); and corporate income tax (1702Q 60 days after the
 * first three quarters, 1702-RT on the 15th of the fourth month after the
 * year). Withholding follows calendar quarters; business and income tax follow
 * the company's fiscal quarters.
 */
final class FilingCalendar
{
    /**
     * @return list<array{kind: string, form: string, title: string, period: string, due: CarbonImmutable}>
     */
    public function upcoming(CarbonImmutable $today, TaxpayerType $taxpayerType, int $fiscalStartMonth = 1): array
    {
        $today = $today->startOfDay();

        $deadlines = [
            $this->businessTax($today, $taxpayerType, $fiscalStartMonth),
            $this->expandedWithholding($today),
            $this->compensationWithholding($today),
            $this->incomeTax($today, $fiscalStartMonth),
        ];

        usort($deadlines, fn (array $a, array $b): int => $a['due'] <=> $b['due']);

        return $deadlines;
    }

    /**
     * @return array{kind: string, form: string, title: string, period: string, due: CarbonImmutable}
     */
    private function businessTax(CarbonImmutable $today, TaxpayerType $taxpayerType, int $fiscalStartMonth): array
    {
        foreach ($this->monthsFrom($today->subMonthsNoOverflow(3)) as $month) {
            if (! $this->closesQuarter($month, $fiscalStartMonth)) {
                continue;
            }

            $due = $month->endOfMonth()->startOfDay()->addDays(25);
            if ($due->lessThan($today)) {
                continue;
            }

            return $taxpayerType === TaxpayerType::Vat
                ? $this->deadline('business', '2550Q', 'Quarterly VAT return', $this->quarterLabel($month, $fiscalStartMonth), $due)
                : $this->deadline('business', '2551Q', 'Quarterly percentage tax return', $this->quarterLabel($month, $fiscalStartMonth), $due);
        }

        throw new LogicException('No business tax deadline found.');
    }

    /**
     * @return array{kind: string, form: string, title: string, period: string, due: CarbonImmutable}
     */
    private function expandedWithholding(CarbonImmutable $today): array
    {
        foreach ($this->monthsFrom($today->subMonthsNoOverflow(2)) as $month) {
            $following = $month->addMonthNoOverflow();

            $deadline = $this->closesQuarter($month, 1)
                ? $this->deadline('ewt', '1601-EQ', 'Quarterly expanded withholding', $this->quarterLabel($month, 1), $following->endOfMonth()->startOfDay())
                : $this->deadline('ewt', '0619-E', 'Monthly expanded withholding', $month->format('F Y'), $following->setDay(10));

            if ($deadline['due']->greaterThanOrEqualTo($today)) {
                return $deadline;
            }
        }

        throw new LogicException('No expanded withholding deadline found.');
    }

    /**
     * @return array{kind: string, form: string, title: string, period: string, due: CarbonImmutable}
     */
    private function compensationWithholding(CarbonImmutable $today): array
    {
        foreach ($this->monthsFrom($today->subMonthsNoOverflow(2)) as $month) {
            $following = $month->addMonthNoOverflow();
            $due = $following->setDay($month->month === 12 ? 15 : 10);

            if ($due->greaterThanOrEqualTo($today)) {
                return $this->deadline('compensation', '1601-C', 'Compensation withholding', $month->format('F Y'), $due);
            }
        }

        throw new LogicException('No compensation withholding deadline found.');
    }

    /**
     * @return array{kind: string, form: string, title: string, period: string, due: CarbonImmutable}
     */
    private function incomeTax(CarbonImmutable $today, int $fiscalStartMonth): array
    {
        foreach ($this->monthsFrom($today->subMonthsNoOverflow(5)) as $month) {
            if (! $this->closesQuarter($month, $fiscalStartMonth)) {
                continue;
            }

            $deadline = $this->fiscalQuarter($month, $fiscalStartMonth) < 4
                ? $this->deadline('income', '1702Q', 'Quarterly income tax', $this->quarterLabel($month, $fiscalStartMonth), $month->endOfMonth()->startOfDay()->addDays(60))
                : $this->deadline('income', '1702-RT', 'Annual income tax', 'FY '.$this->fiscalYear($month, $fiscalStartMonth), $month->addMonthsNoOverflow(4)->setDay(15));

            if ($deadline['due']->greaterThanOrEqualTo($today)) {
                return $deadline;
            }
        }

        throw new LogicException('No income tax deadline found.');
    }

    /**
     * @return array{kind: string, form: string, title: string, period: string, due: CarbonImmutable}
     */
    private function deadline(string $kind, string $form, string $title, string $period, CarbonImmutable $due): array
    {
        return ['kind' => $kind, 'form' => $form, 'title' => $title, 'period' => $period, 'due' => $due];
    }

    /**
     * Two years of month starts from $start, enough to reach any next deadline.
     *
     * @return list<CarbonImmutable>
     */
    private function monthsFrom(CarbonImmutable $start): array
    {
        $months = [];
        $month = $start->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $months[] = $month;
            $month = $month->addMonthNoOverflow();
        }

        return $months;
    }

    private function closesQuarter(CarbonImmutable $month, int $fiscalStartMonth): bool
    {
        return $this->monthOfFiscalYear($month, $fiscalStartMonth) % 3 === 2;
    }

    private function fiscalQuarter(CarbonImmutable $month, int $fiscalStartMonth): int
    {
        return intdiv($this->monthOfFiscalYear($month, $fiscalStartMonth), 3) + 1;
    }

    /** Fiscal years are named for the calendar year they start in. */
    private function fiscalYear(CarbonImmutable $month, int $fiscalStartMonth): int
    {
        return $month->month >= $fiscalStartMonth ? $month->year : $month->year - 1;
    }

    private function quarterLabel(CarbonImmutable $month, int $fiscalStartMonth): string
    {
        return 'Q'.$this->fiscalQuarter($month, $fiscalStartMonth).' '.$this->fiscalYear($month, $fiscalStartMonth);
    }

    /** 0 for the fiscal year's first month through 11 for its last. */
    private function monthOfFiscalYear(CarbonImmutable $month, int $fiscalStartMonth): int
    {
        return (($month->month - $fiscalStartMonth) % 12 + 12) % 12;
    }
}
