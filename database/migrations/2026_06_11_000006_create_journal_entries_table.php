<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->string('number')->nullable(); // assigned at posting under row lock
            $table->date('entry_date');
            $table->text('memo')->nullable();

            // Polymorphic source (invoice, bill, payment, asset disposal, recurring run...). Null = manual JE.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->enum('status', ['draft', 'submitted', 'approved', 'posted', 'reversed'])->default('draft');

            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('reversal_reason')->nullable(); // required on reversal entries

            // Shared document meta (HasDocumentMeta)
            $table->string('reference_no')->nullable();
            $table->string('external_reference_no')->nullable();
            $table->text('remarks')->nullable();

            // Signatory trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();

            $table->bigInteger('total_debits')->default(0);
            $table->bigInteger('total_credits')->default(0);

            $table->timestamps();

            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->unique(['company_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
