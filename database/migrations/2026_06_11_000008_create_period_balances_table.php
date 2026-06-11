<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Balance summarization — keeps TB/BS/P&L fast at scale. Upserted inside
     * PostJournalEntry (and reversals) within the same transaction. (§4.1)
     */
    public function up(): void
    {
        Schema::create('period_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('accounting_periods')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->bigInteger('opening')->default(0); // signed
            $table->bigInteger('debits')->default(0);
            $table->bigInteger('credits')->default(0);
            $table->bigInteger('closing')->default(0); // signed
            $table->timestamps();

            $table->unique(['company_id', 'period_id', 'account_id']);
            $table->index(['company_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_balances');
    }
};
