<?php

declare(strict_types=1);

namespace App\Services\Integration;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Spatie\LaravelData\DataCollection;

/**
 * Maps a Charlie HRMS payroll summary into a balanced journal entry (§14):
 *   Dr Salaries (gross) + Dr Employer contributions
 *      Cr WTax payable + Cr Statutory payables (employee+employer) + Cr Net pay
 * Requires gross_pay = net_pay + withholding_tax + statutory_employee; an
 * inconsistent summary yields an unbalanced entry and is rejected.
 */
final class HrmsPayrollMapper
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly IntegrationAccountMap $accounts,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(Company $company, array $data, ?User $actor): JournalEntry
    {
        $acc = $this->accounts->hrms($company->id);

        $gross = (int) ($data['gross_pay'] ?? 0);
        $employer = (int) ($data['employer_contributions'] ?? 0);
        $withholding = (int) ($data['withholding_tax'] ?? 0);
        $statutoryEmployee = (int) ($data['statutory_employee'] ?? 0);
        $net = (int) ($data['net_pay'] ?? 0);

        $lines = [new JournalLineData(account_id: $acc['salaries'], debit: $gross, memo: 'Gross salaries & wages')];

        if ($employer !== 0) {
            $lines[] = new JournalLineData(account_id: $acc['employer_contributions'], debit: $employer, memo: 'Employer statutory contributions');
        }
        if ($withholding !== 0) {
            $lines[] = new JournalLineData(account_id: $acc['withholding_tax'], credit: $withholding, memo: 'Withholding tax on compensation');
        }
        $statutory = $statutoryEmployee + $employer;
        if ($statutory !== 0) {
            $lines[] = new JournalLineData(account_id: $acc['statutory_payable'], credit: $statutory, memo: 'Statutory payables (SSS/PhilHealth/Pag-IBIG)');
        }
        if ($net !== 0) {
            $lines[] = new JournalLineData(account_id: $acc['net_pay'], credit: $net, memo: 'Net pay disbursed');
        }

        $reference = isset($data['reference']) ? (string) $data['reference'] : null;

        return $this->post->handle(new JournalEntryData(
            company_id: $company->id,
            entry_date: (string) ($data['pay_date'] ?? ''),
            memo: 'Payroll'.($reference !== null ? ' — '.$reference : ''),
            lines: new DataCollection(JournalLineData::class, $lines),
            source_type: 'hrms.payroll',
            external_reference_no: $reference,
            created_by: $actor?->id,
            approved_by: $actor?->id,
        ), $actor);
    }
}
