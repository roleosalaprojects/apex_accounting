<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('fixed_asset_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('accum_depreciation_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('depreciation_expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedSmallInteger('default_useful_life_months')->default(60);
            $table->enum('method', ['straight_line'])->default('straight_line'); // declining_balance reserved (v2)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_category_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('acquisition_date');
            $table->bigInteger('acquisition_cost');
            $table->bigInteger('salvage_value')->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->foreignId('source_bill_line_id')->nullable()->constrained('bill_lines')->nullOnDelete();
            $table->enum('status', ['draft', 'in_service', 'fully_depreciated', 'disposed'])->default('draft');
            $table->date('in_service_date')->nullable();
            $table->date('disposed_at')->nullable();

            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('depreciation_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->bigInteger('amount');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['asset_id', 'period_id']); // one row per asset per period
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_categories');
    }
};
