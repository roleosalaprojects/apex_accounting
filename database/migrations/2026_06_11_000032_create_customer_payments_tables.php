<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->date('payment_date');
            $table->enum('method', ['cash', 'bank', 'gcash', 'maya', 'check', 'other'])->default('cash');
            $table->foreignId('deposit_to_account_id')->constrained('accounts')->restrictOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('ewt_withheld')->default(0); // when customer is a withholding agent
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['posted', 'voided'])->default('posted');
            $table->string('collection_receipt_no')->nullable(); // supplementary doc (RR 7-2024)

            $table->string('reference_no')->nullable();
            $table->string('external_reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'payment_date']);
        });

        Schema::create('payment_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->timestamps();

            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_applications');
        Schema::dropIfExists('customer_payments');
    }
};
