<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const VALUE_DIVISOR = 100_000_000;

    public function up(): void
    {
        Schema::table('item_valuations', function (Blueprint $table): void {
            // Carrying value of the stock on hand, in centavos. It moves by
            // exactly what is posted to the item's inventory account, so the
            // stock subledger equals the ledger to the centavo (§9).
            $table->bigInteger('value')->default(0)->after('avg_cost_x10000');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('item_valuations', function (Blueprint $table): void {
            $table->dropColumn('value');
        });
    }

    /**
     * Start each item at its implied value (quantity × average, as it was
     * derived until now), then fold per-sale rounding differences into the
     * largest holding on each inventory account so stock equals the ledger.
     * A difference bigger than one centavo per posting on the account is not
     * rounding; it is left for ledger:verify to report.
     */
    public function backfill(): void
    {
        $rows = DB::table('item_valuations')
            ->join('items', 'items.id', '=', 'item_valuations.item_id')
            ->where('items.type', 'inventory')
            ->orderBy('item_valuations.id')
            ->get(['item_valuations.id', 'item_valuations.company_id', 'item_valuations.qty_units',
                'item_valuations.avg_cost_x10000', 'items.inventory_account_id']);

        /** @var array<string, list<array{id: int, qty: int, value: int}>> $holdings */
        $holdings = [];
        foreach ($rows as $row) {
            $qty = (int) $row->qty_units;
            $value = $qty === 0 ? 0 : self::roundDiv($qty * (int) $row->avg_cost_x10000, self::VALUE_DIVISOR);
            DB::table('item_valuations')->where('id', $row->id)->update(['value' => $value]);

            if ($row->inventory_account_id !== null) {
                $holdings[$row->company_id.':'.$row->inventory_account_id][] = ['id' => (int) $row->id, 'qty' => $qty, 'value' => $value];
            }
        }

        foreach ($holdings as $key => $items) {
            [$companyId, $accountId] = array_map('intval', explode(':', $key));

            $postings = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.company_id', $companyId)
                ->whereIn('journal_entries.status', ['posted', 'reversed'])
                ->where('journal_lines.account_id', $accountId)
                ->selectRaw('COUNT(*) as n, COALESCE(SUM(journal_lines.debit), 0) - COALESCE(SUM(journal_lines.credit), 0) as balance')
                ->first();

            $difference = (int) ($postings->balance ?? 0) - array_sum(array_column($items, 'value'));
            $stocked = array_values(array_filter($items, fn (array $item): bool => $item['qty'] > 0));
            if ($difference === 0 || $stocked === [] || abs($difference) > (int) ($postings->n ?? 0)) {
                continue;
            }

            usort($stocked, fn (array $a, array $b): int => $b['value'] <=> $a['value']);
            $largest = $stocked[0];
            $value = $largest['value'] + $difference;

            DB::table('item_valuations')->where('id', $largest['id'])->update([
                'value' => $value,
                'avg_cost_x10000' => self::roundDiv($value * self::VALUE_DIVISOR, $largest['qty']),
            ]);
        }
    }

    /** Integer division rounding half away from zero, as VatMath::roundDiv. */
    private static function roundDiv(int $num, int $den): int
    {
        return $num < 0
            ? -intdiv((-$num * 2) + $den, $den * 2)
            : intdiv(($num * 2) + $den, $den * 2);
    }
};
