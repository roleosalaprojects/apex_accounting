<?php

declare(strict_types=1);

use App\Enums\StockMovementKind;
use App\Models\Bill;
use App\Models\DebitMemo;
use App\Models\InventoryAdjustment;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The stock ledger: one signed row per movement of an item, so a stock
     * card can be read back and an as-of valuation computed. Existing stock
     * history is rebuilt from the documents that moved it.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->date('moved_on');
            $table->string('kind', 20);
            $table->bigInteger('qty_units');   // signed ten-thousandths
            $table->bigInteger('value');       // signed centavos
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'item_id', 'moved_on']);
            $table->index(['source_type', 'source_id']);
        });

        $this->rebuildHistory();
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }

    /**
     * Replay every document that moved stock, in the order it was posted:
     * bills receive at line cost, invoices issue at the cost of sales they
     * recorded, debit memos take stock out at what their entry credited,
     * counts move by what they recorded, and voids undo their document on
     * the reversal's date.
     */
    private function rebuildHistory(): void
    {
        $stocked = DB::table('items')->where('type', 'inventory')->pluck('id')->all();
        if ($stocked === []) {
            return;
        }
        $now = now();
        $rows = [];

        $reversalDate = function (string $sourceType, int $sourceId): ?string {
            $original = DB::table('journal_entries')->where('source_type', $sourceType)->where('source_id', $sourceId)
                ->whereNull('reversal_of_id')->orderBy('id')->first(['id']);
            if ($original === null) {
                return null;
            }
            $reversal = DB::table('journal_entries')->where('reversal_of_id', $original->id)->orderByDesc('id')->first(['entry_date']);

            return $reversal === null ? null : substr((string) $reversal->entry_date, 0, 10);
        };

        $bills = DB::table('bills')->where('is_opening', false)
            ->whereIn('status', ['posted', 'partially_paid', 'paid', 'voided'])->orderBy('id')->get();
        foreach ($bills as $bill) {
            $lines = DB::table('bill_lines')->where('bill_id', $bill->id)->whereIn('item_id', $stocked)->orderBy('line_no')->get();
            foreach ($lines as $line) {
                $units = $this->units((string) $line->qty);
                $rows[] = $this->row($bill->company_id, $line->item_id, substr((string) $bill->bill_date, 0, 10), StockMovementKind::Receipt, $units, (int) $line->line_total, Bill::class, $bill->id, $bill->number, $line->description, $bill->created_by, $bill->created_at, $now);
                if ($bill->status === 'voided') {
                    $date = $reversalDate(Bill::class, $bill->id) ?? substr((string) $bill->updated_at, 0, 10);
                    $rows[] = $this->row($bill->company_id, $line->item_id, $date, StockMovementKind::VoidOut, -$units, -(int) $line->line_total, Bill::class, $bill->id, $bill->number, $line->description, null, $bill->updated_at, $now);
                }
            }
        }

        $invoices = DB::table('invoices')->where('is_opening', false)
            ->whereIn('status', ['posted', 'partially_paid', 'paid', 'voided'])->orderBy('id')->get();
        foreach ($invoices as $invoice) {
            $lines = DB::table('invoice_lines')->where('invoice_id', $invoice->id)->whereIn('item_id', $stocked)->orderBy('line_no')->get();
            foreach ($lines as $line) {
                $units = $this->units((string) $line->qty);
                $cogs = (int) ($line->cogs ?? 0);
                $rows[] = $this->row($invoice->company_id, $line->item_id, substr((string) $invoice->invoice_date, 0, 10), StockMovementKind::Issue, -$units, -$cogs, Invoice::class, $invoice->id, $invoice->number, $line->description, $invoice->created_by, $invoice->created_at, $now);
                if ($invoice->status === 'voided') {
                    $date = $reversalDate(Invoice::class, $invoice->id) ?? substr((string) $invoice->updated_at, 0, 10);
                    $rows[] = $this->row($invoice->company_id, $line->item_id, $date, StockMovementKind::VoidIn, $units, $cogs, Invoice::class, $invoice->id, $invoice->number, $line->description, null, $invoice->updated_at, $now);
                }
            }
        }

        $memos = DB::table('debit_memos')->whereIn('status', ['posted', 'applied'])->orderBy('id')->get();
        foreach ($memos as $memo) {
            $lines = DB::table('debit_memo_lines')->where('debit_memo_id', $memo->id)->whereIn('item_id', $stocked)->orderBy('line_no')->get();
            foreach ($lines as $line) {
                // What the entry actually credited to inventory for this line (stock left at average cost).
                $credited = $memo->journal_entry_id === null ? null : DB::table('journal_lines')
                    ->where('journal_entry_id', $memo->journal_entry_id)
                    ->where('memo', 'Purchase return — '.$line->description)
                    ->where('credit', '>', 0)->orderBy('id')->value('credit');
                $value = (int) ($credited ?? $line->line_total);
                $rows[] = $this->row($memo->company_id, $line->item_id, substr((string) $memo->memo_date, 0, 10), StockMovementKind::ReturnOut, -$this->units((string) $line->qty), -$value, DebitMemo::class, $memo->id, $memo->number, $line->description, $memo->created_by, $memo->created_at, $now);
            }
        }

        foreach (DB::table('inventory_adjustments')->whereIn('item_id', $stocked)->orderBy('id')->get() as $adjustment) {
            $rows[] = $this->row($adjustment->company_id, $adjustment->item_id, substr((string) $adjustment->adjustment_date, 0, 10), StockMovementKind::Adjustment, (int) $adjustment->qty_units_change, (int) $adjustment->value_change, InventoryAdjustment::class, $adjustment->id, null, $adjustment->reason, $adjustment->created_by, $adjustment->created_at, $now);
        }

        // Posting order: by the time each document was created, voids last.
        usort($rows, fn (array $a, array $b): int => [$a['_order'], $a['_seq']] <=> [$b['_order'], $b['_seq']]);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('stock_movements')->insert(array_map(fn (array $row): array => array_diff_key($row, ['_order' => 1, '_seq' => 1]), $chunk));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $companyId, int $itemId, string $date, StockMovementKind $kind, int $units, int $value, string $sourceType, int $sourceId, ?string $reference, ?string $description, ?int $createdBy, mixed $createdAt, mixed $now): array
    {
        static $seq = 0;

        return [
            'company_id' => $companyId, 'item_id' => $itemId, 'moved_on' => $date, 'kind' => $kind->value,
            'qty_units' => $units, 'value' => $value, 'source_type' => $sourceType, 'source_id' => $sourceId,
            'reference' => $reference, 'description' => $description === null ? null : mb_substr($description, 0, 255), 'created_by' => $createdBy,
            'created_at' => $createdAt ?? $now, 'updated_at' => $now,
            '_order' => (string) ($createdAt ?? $now), '_seq' => $seq++,
        ];
    }

    private function units(string $qty): int
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim($qty, '-')), 2, '');
        $units = ((int) $whole) * 10_000 + (int) substr(str_pad($fraction, 4, '0'), 0, 4);

        return str_starts_with($qty, '-') ? -$units : $units;
    }
};
