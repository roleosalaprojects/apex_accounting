<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_memos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->date('memo_date');
            $table->enum('status', ['draft', 'submitted', 'approved', 'posted', 'applied', 'voided'])->default('draft');
            $table->enum('pricing_mode', ['vat_inclusive', 'vat_exclusive'])->default('vat_inclusive');

            $table->bigInteger('vatable_sales')->default(0);
            $table->bigInteger('vat_amount')->default(0);
            $table->bigInteger('exempt_sales')->default(0);
            $table->bigInteger('zero_rated_sales')->default(0);
            $table->bigInteger('total')->default(0);

            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->text('memo')->nullable();
            $table->string('reference_no')->nullable();
            $table->string('external_reference_no')->nullable();
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
        });

        Schema::create('credit_memo_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_memo_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('line_no');
            $table->foreignId('item_id')->nullable();
            $table->string('description');
            $table->decimal('qty', 15, 4)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->foreignId('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->bigInteger('line_total')->default(0);
            $table->bigInteger('vat_amount')->default(0);
            $table->foreignId('income_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->index('credit_memo_id');
        });

        Schema::create('credit_memo_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_memo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_memo_applications');
        Schema::dropIfExists('credit_memo_lines');
        Schema::dropIfExists('credit_memos');
    }
};
