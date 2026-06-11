<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'posted', 'partially_paid', 'paid', 'voided'])->default('draft');
            $table->enum('pricing_mode', ['vat_inclusive', 'vat_exclusive'])->default('vat_exclusive');
            $table->boolean('is_opening')->default(false);

            $table->bigInteger('vatable_purchases')->default(0);
            $table->bigInteger('input_vat')->default(0);
            $table->bigInteger('exempt_purchases')->default(0);
            $table->bigInteger('total')->default(0);

            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('memo')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('external_reference_no')->nullable(); // vendor invoice no.
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'bill_date']);
            $table->index(['company_id', 'vendor_id']);
        });

        Schema::create('bill_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('line_no');
            $table->foreignId('item_id')->nullable();
            $table->string('description');
            $table->decimal('qty', 15, 4)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->enum('vat_bucket', ['direct_vatable', 'direct_exempt', 'common'])->nullable();
            $table->bigInteger('line_total')->default(0); // net (cost may include capitalized exempt VAT)
            $table->bigInteger('vat_amount')->default(0);
            $table->foreignId('expense_or_asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->index('bill_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
    }
};
