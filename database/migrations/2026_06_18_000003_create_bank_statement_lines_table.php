<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Imported bank statement lines (§8): a staging area for CSV bank statements,
     * matched/posted to the ledger one line at a time as a reconciliation aid.
     */
    public function up(): void
    {
        Schema::create('bank_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->date('txn_date');
            $table->string('description')->default('');
            $table->string('reference')->nullable();
            $table->bigInteger('amount');          // signed minor: + deposit, - withdrawal
            $table->bigInteger('balance')->nullable();
            $table->string('status', 20)->default('unmatched'); // unmatched | matched | ignored
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('csv');
            $table->string('import_ref')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'bank_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
    }
};
