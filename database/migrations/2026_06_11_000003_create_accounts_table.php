<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('code', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['asset', 'liability', 'equity', 'income', 'expense']);
            $table->enum('subtype', [
                'cash', 'bank', 'accounts_receivable', 'inventory', 'other_current_asset',
                'fixed_asset', 'accumulated_depreciation', 'other_asset',
                'accounts_payable', 'credit_card', 'vat_payable', 'withholding_payable',
                'other_current_liability', 'long_term_liability',
                'equity', 'retained_earnings',
                'income', 'other_income',
                'cogs', 'expense', 'depreciation_expense', 'other_expense',
            ]);
            $table->enum('normal_balance', ['debit', 'credit']);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'subtype']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
