<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An invoice or bill line raised from an order remembers which order
     * line it covered, so a void can hand the quantity back exactly.
     */
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table): void {
            $table->foreignId('sales_order_line_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('bill_lines', function (Blueprint $table): void {
            $table->foreignId('purchase_order_line_id')->nullable()->constrained()->nullOnDelete();
        });

        // Lines already raised from an order: match them to the order line with the same item and description.
        foreach (DB::table('invoices')->whereNotNull('sales_order_id')->get(['id', 'sales_order_id']) as $invoice) {
            foreach (DB::table('invoice_lines')->where('invoice_id', $invoice->id)->get(['id', 'item_id', 'description']) as $line) {
                $orderLine = DB::table('sales_order_lines')->where('sales_order_id', $invoice->sales_order_id)
                    ->where('description', $line->description)->where(fn ($q) => $q->where('item_id', $line->item_id)->orWhereNull('item_id'))
                    ->orderBy('id')->value('id');
                if ($orderLine !== null) {
                    DB::table('invoice_lines')->where('id', $line->id)->update(['sales_order_line_id' => $orderLine]);
                }
            }
        }
        foreach (DB::table('bills')->whereNotNull('purchase_order_id')->get(['id', 'purchase_order_id']) as $bill) {
            foreach (DB::table('bill_lines')->where('bill_id', $bill->id)->get(['id', 'item_id', 'description']) as $line) {
                $orderLine = DB::table('purchase_order_lines')->where('purchase_order_id', $bill->purchase_order_id)
                    ->where('description', $line->description)->where(fn ($q) => $q->where('item_id', $line->item_id)->orWhereNull('item_id'))
                    ->orderBy('id')->value('id');
                if ($orderLine !== null) {
                    DB::table('bill_lines')->where('id', $line->id)->update(['purchase_order_line_id' => $orderLine]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('bill_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_order_line_id'));
        Schema::table('invoice_lines', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_order_line_id'));
    }
};
