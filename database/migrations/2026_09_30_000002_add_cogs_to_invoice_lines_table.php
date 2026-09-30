<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            // Cost of sales posted for a stocked item's line (centavos), so a
            // void can put the goods back at exactly the cost that left.
            $table->bigInteger('cogs')->nullable()->after('vat_amount');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->dropColumn('cogs');
        });
    }

    /**
     * Lines posted before this column existed: take each invoice's cost-of-
     * sales entry (the inventory credits, by account) and spread it over the
     * invoice's stocked lines on that account in proportion to their amount,
     * the last line taking the remainder so the total is exact.
     */
    public function backfill(): void
    {
        $credits = DB::table('journal_entries')
            ->join('journal_lines', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.source_type', 'App\\Models\\Invoice')
            ->where('journal_entries.memo', 'like', 'COGS for %')
            ->whereNull('journal_entries.reversal_of_id')
            ->where('journal_lines.credit', '>', 0)
            ->groupBy('journal_entries.source_id', 'journal_lines.account_id')
            ->selectRaw('journal_entries.source_id as invoice_id, journal_lines.account_id, SUM(journal_lines.credit) as cogs')
            ->get();

        foreach ($credits as $credit) {
            $lines = DB::table('invoice_lines')
                ->join('items', 'items.id', '=', 'invoice_lines.item_id')
                ->where('invoice_lines.invoice_id', $credit->invoice_id)
                ->where('items.type', 'inventory')
                ->where('items.inventory_account_id', $credit->account_id)
                ->orderBy('invoice_lines.line_no')
                ->get(['invoice_lines.id', 'invoice_lines.line_total']);

            $total = (int) $lines->sum('line_total');
            $left = (int) $credit->cogs;
            foreach ($lines as $index => $line) {
                $share = $index === $lines->count() - 1 || $total === 0
                    ? $left
                    : intdiv((int) $credit->cogs * (int) $line->line_total, $total);
                DB::table('invoice_lines')->where('id', $line->id)->update(['cogs' => $share]);
                $left -= $share;
            }
        }
    }
};
