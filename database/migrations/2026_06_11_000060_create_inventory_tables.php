<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 50);
            $table->string('name');
            $table->enum('type', ['inventory', 'non_inventory', 'service'])->default('inventory');
            $table->boolean('is_vat_exempt_item')->default(false); // TRUE for all rice SKUs (§9)
            $table->foreignId('income_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('cogs_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('inventory_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->bigInteger('default_sales_price')->default(0);
            $table->bigInteger('default_purchase_price')->default(0);
            $table->string('unit', 30)->default('pc'); // e.g. sack_25kg, sack_50kg, kg
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'sku']);
        });

        Schema::create('item_valuations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            // Running quantity in ten-thousandths (integer) and weighted-average
            // unit cost in centavos × 10000 (§9). Both integer for no-float math.
            $table->bigInteger('qty_units')->default(0);
            $table->bigInteger('avg_cost_x10000')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'item_id']);
        });

        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->date('adjustment_date');
            $table->bigInteger('qty_units_change'); // signed ten-thousandths
            $table->bigInteger('value_change');     // signed centavos
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'item_id']);
        });

        // Wire up the deferred item_id FKs on document line tables. SQLite cannot
        // ALTER TABLE ADD a foreign key, so this only runs on the production driver.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            foreach (['invoice_lines', 'credit_memo_lines', 'bill_lines', 'debit_memo_lines'] as $t) {
                Schema::table($t, function (Blueprint $table): void {
                    $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            foreach (['invoice_lines', 'credit_memo_lines', 'bill_lines', 'debit_memo_lines'] as $t) {
                Schema::table($t, function (Blueprint $table): void {
                    $table->dropForeign(['item_id']);
                });
            }
        }
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('item_valuations');
        Schema::dropIfExists('items');
    }
};
