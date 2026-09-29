<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Profit and loss split by one reporting dimension (department, project, fund
 * or branch) over a date range: income, expenses and net income per tag, plus
 * an "Untagged" row, so the rows always add up to the P&L report's totals.
 */
final class DimensionProfitAndLossReport
{
    /** dimension => [journal_lines column, model] */
    public const DIMENSIONS = [
        'department' => ['department_id', Department::class],
        'project' => ['project_id', Project::class],
        'fund' => ['fund_id', Fund::class],
        'branch' => ['branch_id', Branch::class],
    ];

    /**
     * @return array{rows: list<array{code: string|null, name: string, income: int, expenses: int, net: int}>, total_income: int, total_expense: int, net_income: int}
     */
    public function build(int $companyId, string $dimension, string $from, string $asOf): array
    {
        if (! isset(self::DIMENSIONS[$dimension])) {
            throw new InvalidArgumentException("Unknown dimension [{$dimension}].");
        }

        [$column, $model] = self::DIMENSIONS[$dimension];
        $byTag = $this->amountsByTag($companyId, $column, $from, $asOf);

        /** @var class-string<Model> $model */
        $tags = $model::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $rows = [];
        foreach ($tags as $tag) {
            $amounts = $byTag[(int) $tag->getKey()] ?? ['income' => 0, 'expenses' => 0];
            $rows[] = $this->row((string) $tag->getAttribute('code'), (string) $tag->getAttribute('name'), $amounts);
        }
        if (isset($byTag['untagged'])) {
            $rows[] = $this->row(null, 'Untagged', $byTag['untagged']);
        }

        $income = array_sum(array_column($rows, 'income'));
        $expenses = array_sum(array_column($rows, 'expenses'));

        return ['rows' => $rows, 'total_income' => $income, 'total_expense' => $expenses, 'net_income' => $income - $expenses];
    }

    /**
     * Income, expenses and net income tagged to one dimension value.
     *
     * @return array{income: int, expenses: int, net: int}
     */
    public function totalsFor(int $companyId, string $dimension, int $tagId, string $from, string $asOf): array
    {
        if (! isset(self::DIMENSIONS[$dimension])) {
            throw new InvalidArgumentException("Unknown dimension [{$dimension}].");
        }

        $amounts = $this->amountsByTag($companyId, self::DIMENSIONS[$dimension][0], $from, $asOf, $tagId)[$tagId]
            ?? ['income' => 0, 'expenses' => 0];

        return [...$amounts, 'net' => $amounts['income'] - $amounts['expenses']];
    }

    /**
     * @return array<int|string, array{income: int, expenses: int}> tag id (or 'untagged') => amounts
     */
    private function amountsByTag(int $companyId, string $column, string $from, string $asOf, ?int $onlyTag = null): array
    {
        $sums = DB::table('journal_lines')
            ->join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereIn('accounts.type', [AccountType::Income->value, AccountType::Expense->value])
            ->whereDate('journal_entries.entry_date', '>=', $from)
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->when($onlyTag !== null, fn ($query) => $query->where("journal_lines.{$column}", $onlyTag))
            ->groupBy("journal_lines.{$column}", 'accounts.type')
            ->selectRaw("journal_lines.{$column} as tag, accounts.type as type, SUM(journal_lines.debit) as d, SUM(journal_lines.credit) as c")
            ->get();

        $byTag = [];
        foreach ($sums as $sum) {
            $key = $sum->tag === null ? 'untagged' : (int) $sum->tag;
            $byTag[$key] ??= ['income' => 0, 'expenses' => 0];

            if ($sum->type === AccountType::Income->value) {
                $byTag[$key]['income'] += (int) $sum->c - (int) $sum->d;
            } else {
                $byTag[$key]['expenses'] += (int) $sum->d - (int) $sum->c;
            }
        }

        return $byTag;
    }

    /**
     * @param  array{income: int, expenses: int}  $amounts
     * @return array{code: string|null, name: string, income: int, expenses: int, net: int}
     */
    private function row(?string $code, string $name, array $amounts): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'income' => $amounts['income'],
            'expenses' => $amounts['expenses'],
            'net' => $amounts['income'] - $amounts['expenses'],
        ];
    }
}
