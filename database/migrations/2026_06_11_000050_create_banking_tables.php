<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete(); // GL cash/bank account
            $table->string('bank_name')->nullable();
            $table->string('account_no')->nullable();
            // NOTE: current_balance is ALWAYS derived from the GL (§8), never stored.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'account_id']);
        });

        Schema::create('reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('statement_date');
            $table->bigInteger('statement_ending_balance');
            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'bank_account_id']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_line_id')->constrained('journal_lines')->cascadeOnDelete();
            $table->boolean('is_cleared')->default(true);
            $table->timestamps();

            $table->unique(['reconciliation_id', 'journal_line_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('bank_accounts');
    }
};
