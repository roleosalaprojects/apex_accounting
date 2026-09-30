<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Orders are fulfilled in parts: sales orders ship on delivery receipts and
     * are invoiced as delivered; purchase orders are billed as goods arrive.
     * Each line remembers how much has moved, and every invoice or bill
     * remembers the order it came from.
     */
    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table): void {
            $table->decimal('delivered_qty', 15, 4)->default(0)->after('qty');
            $table->decimal('invoiced_qty', 15, 4)->default(0)->after('delivered_qty');
        });
        Schema::table('purchase_order_lines', function (Blueprint $table): void {
            $table->decimal('billed_qty', 15, 4)->default(0)->after('qty');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::table('bills', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->string('number')->nullable();
            $table->date('delivery_date');
            $table->string('received_by')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'delivery_date']);
        });
        Schema::create('delivery_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('qty', 15, 4);
            $table->timestamps();
        });

        // Orders already converted whole: the one invoice/bill covered every line.
        foreach (DB::table('sales_orders')->whereNotNull('invoice_id')->get(['id', 'invoice_id']) as $order) {
            DB::table('invoices')->where('id', $order->invoice_id)->update(['sales_order_id' => $order->id]);
            DB::table('sales_order_lines')->where('sales_order_id', $order->id)->update(['invoiced_qty' => DB::raw('qty')]);
        }
        foreach (DB::table('purchase_orders')->whereNotNull('bill_id')->get(['id', 'bill_id']) as $order) {
            DB::table('bills')->where('id', $order->bill_id)->update(['purchase_order_id' => $order->id]);
            DB::table('purchase_order_lines')->where('purchase_order_id', $order->id)->update(['billed_qty' => DB::raw('qty')]);
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            if (! DB::table('document_sequences')->where('company_id', $companyId)->where('key', 'delivery')->exists()) {
                DB::table('document_sequences')->insert([
                    'company_id' => $companyId, 'key' => 'delivery', 'prefix' => 'DR', 'next_number' => 1, 'padding' => 6,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_lines');
        Schema::dropIfExists('deliveries');
        Schema::table('bills', fn (Blueprint $table) => $table->dropConstrainedForeignId('purchase_order_id'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_order_id'));
        Schema::table('purchase_order_lines', fn (Blueprint $table) => $table->dropColumn('billed_qty'));
        Schema::table('sales_order_lines', fn (Blueprint $table) => $table->dropColumn(['delivered_qty', 'invoiced_qty']));
    }
};
