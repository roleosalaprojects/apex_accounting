<?php

declare(strict_types=1);

namespace App\Actions\Tax;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\DuplicateAllocationException;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use App\Models\VatAllocation;
use App\Services\Tax\VatMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Quarterly allocation of common (overhead) input VAT (§5.4).
 *
 *   ratio_creditable = VATable sales / (VATable + VAT-exempt sales)   [net of VAT]
 *   creditable   = common input VAT × ratio   -> Dr 1400 Input VAT
 *   non_credit   = common input VAT − creditable -> Dr 6850 Non-creditable Input VAT
 *                                                Cr 1410 Deferred Input VAT — Common
 *
 * Idempotent per quarter: re-running throws unless the prior allocation is reversed.
 */
final class AllocateCommonInputVat
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly VatMath $vat,
    ) {}

    public function handle(Company $company, int $fiscalYear, int $quarter, ?User $actor = null): VatAllocation
    {
        if ($quarter < 1 || $quarter > 4) {
            throw new RuntimeException('Quarter must be 1..4.');
        }

        return DB::transaction(function () use ($company, $fiscalYear, $quarter, $actor): VatAllocation {
            $existing = VatAllocation::query()
                ->where('company_id', $company->id)
                ->where('fiscal_year', $fiscalYear)
                ->where('quarter', $quarter)
                ->first();

            if ($existing !== null) {
                throw DuplicateAllocationException::make("FY{$fiscalYear} Q{$quarter}");
            }

            $periodIds = $this->quarterPeriodIds($company, $fiscalYear, $quarter);
            if ($periodIds === []) {
                throw new RuntimeException("No periods for FY{$fiscalYear} Q{$quarter}.");
            }

            [$vatable, $exempt] = $this->salesSplit($company, $periodIds);
            $commonInputVat = $this->commonInputVat($company, $periodIds);

            $denominator = $vatable + $exempt;
            $ratioBp = $denominator > 0 ? $this->vat->roundDiv($vatable * 10_000, $denominator) : 0;
            $creditable = $denominator > 0 ? $this->vat->roundDiv($commonInputVat * $vatable, $denominator) : 0;
            $nonCreditable = $commonInputVat - $creditable;

            $entry = $this->postAllocationEntry($company, $fiscalYear, $quarter, $creditable, $nonCreditable, $commonInputVat, $actor);

            return VatAllocation::query()->create([
                'company_id' => $company->id,
                'fiscal_year' => $fiscalYear,
                'quarter' => $quarter,
                'vatable_sales' => $vatable,
                'exempt_sales' => $exempt,
                'common_input_vat' => $commonInputVat,
                'ratio_creditable_bp' => $ratioBp,
                'creditable' => $creditable,
                'non_creditable' => $nonCreditable,
                'journal_entry_id' => $entry?->id,
            ]);
        });
    }

    /**
     * @return array<int, int>
     */
    private function quarterPeriodIds(Company $company, int $fiscalYear, int $quarter): array
    {
        $from = (($quarter - 1) * 3) + 1;
        $to = $quarter * 3;

        return AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('fiscal_year', $fiscalYear)
            ->whereBetween('period_no', [$from, $to])
            ->orderBy('period_no')
            ->pluck('id')
            ->all();
    }

    /**
     * Net sales (credit − debit) on income accounts, split by tax treatment.
     *
     * @param  array<int, int>  $periodIds
     * @return array{0: int, 1: int} [vatable, exempt]
     */
    private function salesSplit(Company $company, array $periodIds): array
    {
        $rows = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->leftJoin('tax_codes', 'journal_lines.tax_code_id', '=', 'tax_codes.id')
            ->where('journal_entries.company_id', $company->id)
            ->whereIn('journal_entries.period_id', $periodIds)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->where('accounts.type', AccountType::Income->value)
            ->groupBy('tax_codes.code')
            ->select(
                'tax_codes.code as tax_code',
                DB::raw('SUM(journal_lines.credit) - SUM(journal_lines.debit) as net'),
            )
            ->get();

        $vatable = 0;
        $exempt = 0;
        foreach ($rows as $row) {
            $net = (int) $row->net;
            if ($row->tax_code === 'EXEMPT') {
                $exempt += $net;
            } else {
                // VAT12 and ZERO (zero-rated) are both taxable for the ratio.
                $vatable += $net;
            }
        }

        return [$vatable, $exempt];
    }

    /**
     * Accumulated common input VAT = net debit on 1410 for the quarter.
     *
     * @param  array<int, int>  $periodIds
     */
    private function commonInputVat(Company $company, array $periodIds): int
    {
        $account = Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', '1410')
            ->firstOrFail();

        $row = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.company_id', $company->id)
            ->whereIn('journal_entries.period_id', $periodIds)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->where('journal_lines.account_id', $account->id)
            ->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as net')
            ->first();

        return (int) ($row->net ?? 0);
    }

    private function postAllocationEntry(
        Company $company,
        int $fiscalYear,
        int $quarter,
        int $creditable,
        int $nonCreditable,
        int $commonInputVat,
        ?User $actor,
    ): ?JournalEntry {
        if ($commonInputVat === 0) {
            return null;
        }

        $lines = [];
        if ($creditable > 0) {
            $lines[] = new JournalLineData(account_id: $this->accountId($company, '1400'), debit: $creditable, memo: 'Creditable common input VAT');
        }
        if ($nonCreditable > 0) {
            $lines[] = new JournalLineData(account_id: $this->accountId($company, '6850'), debit: $nonCreditable, memo: 'Non-creditable common input VAT');
        }
        $lines[] = new JournalLineData(account_id: $this->accountId($company, '1410'), credit: $commonInputVat, memo: 'Allocate deferred common input VAT');

        $lastPeriod = AccountingPeriod::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('fiscal_year', $fiscalYear)
            ->where('period_no', $quarter * 3)
            ->firstOrFail();

        $data = new JournalEntryData(
            company_id: $company->id,
            entry_date: $lastPeriod->ends_on->toDateString(),
            memo: "Common input VAT allocation FY{$fiscalYear} Q{$quarter}",
            lines: new DataCollection(JournalLineData::class, $lines),
            approved_by: $actor?->id,
            created_by: $actor?->id,
        );

        return $this->post->handle($data, $actor);
    }

    private function accountId(Company $company, string $code): int
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->value('id');
    }
}
