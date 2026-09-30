<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\ItemType;
use App\Enums\JournalStatus;
use App\Models\Item;
use App\Services\Tax\VatMath;
use Illuminate\Support\Facades\DB;

/**
 * Stock summary (§9): every stocked item's opening balance, what came in and
 * went out over the period, and the closing quantity, average cost and
 * value — with the inventory accounts' ledger balance as of the same date,
 * so the valuation can be seen to tie.
 */
final class StockSummaryReport
{
    public function __construct(private readonly VatMath $math) {}

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, int>, ledger_value: int}
     */
    public function build(int $companyId, string $from, string $asOf): array
    {
        $before = DB::table('stock_movements')
            ->where('company_id', $companyId)->whereDate('moved_on', '<', $from)
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(qty_units) as units, SUM(value) as value')
            ->get()->keyBy('item_id');

        $during = DB::table('stock_movements')
            ->where('company_id', $companyId)
            ->whereDate('moved_on', '>=', $from)->whereDate('moved_on', '<=', $asOf)
            ->groupBy('item_id')
            ->selectRaw('item_id,
                SUM(CASE WHEN qty_units > 0 THEN qty_units ELSE 0 END) as in_units,
                SUM(CASE WHEN qty_units > 0 THEN value ELSE 0 END) as in_value,
                SUM(CASE WHEN qty_units < 0 THEN -qty_units ELSE 0 END) as out_units,
                SUM(CASE WHEN qty_units < 0 THEN -value ELSE 0 END) as out_value')
            ->get()->keyBy('item_id');

        $items = Item::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)->where('type', ItemType::Inventory)
            ->whereIn('id', $before->keys()->merge($during->keys())->unique())
            ->orderBy('sku')->get();

        $rows = [];
        $totals = ['opening_value' => 0, 'in_value' => 0, 'out_value' => 0, 'closing_value' => 0];
        foreach ($items as $item) {
            $b = $before->get($item->id);
            $d = $during->get($item->id);
            $openingUnits = (int) ($b->units ?? 0);
            $openingValue = (int) ($b->value ?? 0);
            $inUnits = (int) ($d->in_units ?? 0);
            $inValue = (int) ($d->in_value ?? 0);
            $outUnits = (int) ($d->out_units ?? 0);
            $outValue = (int) ($d->out_value ?? 0);
            $closingUnits = $openingUnits + $inUnits - $outUnits;
            $closingValue = $openingValue + $inValue - $outValue;

            $rows[] = [
                'item_id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'unit' => $item->unit,
                'opening_units' => $openingUnits,
                'opening_value' => $openingValue,
                'in_units' => $inUnits,
                'in_value' => $inValue,
                'out_units' => $outUnits,
                'out_value' => $outValue,
                'closing_units' => $closingUnits,
                'closing_value' => $closingValue,
                'avg_cost' => $closingUnits > 0 ? $this->math->roundDiv($closingValue * 10_000, $closingUnits) : 0,
            ];
            $totals['opening_value'] += $openingValue;
            $totals['in_value'] += $inValue;
            $totals['out_value'] += $outValue;
            $totals['closing_value'] += $closingValue;
        }

        return ['rows' => $rows, 'totals' => $totals, 'ledger_value' => $this->ledgerValue($companyId, $asOf)];
    }

    /** The balance of every account that holds stocked items, as of the date. */
    private function ledgerValue(int $companyId, string $asOf): int
    {
        $accountIds = DB::table('items')
            ->where('company_id', $companyId)->where('type', ItemType::Inventory->value)
            ->whereNotNull('inventory_account_id')->distinct()->pluck('inventory_account_id');

        return (int) DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->whereIn('journal_entries.status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])
            ->whereDate('journal_entries.entry_date', '<=', $asOf)
            ->whereIn('journal_lines.account_id', $accountIds)
            ->selectRaw('COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
            ->value('balance');
    }
}
