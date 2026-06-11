<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->string('voucher_no')->nullable(); // payment_voucher sequence (Check/Disbursement Voucher)
            $table->date('payment_date');
            $table->enum('method', ['cash', 'bank', 'gcash', 'maya', 'check', 'other'])->default('bank');
            $table->foreignId('paid_from_account_id')->constrained('accounts')->restrictOnDelete();
            $table->bigInteger('gross_applied')->default(0); // total bill amount settled
            $table->bigInteger('ewt')->default(0);          // expanded withholding tax
            $table->bigInteger('net_paid')->default(0);     // cash out = gross − ewt
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['posted', 'voided'])->default('posted');

            $table->string('reference_no')->nullable();
            $table->string('external_reference_no')->nullable(); // check no.
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

        Schema::create('bill_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vendor_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount');
            $table->timestamps();

            $table->index('bill_id');
        });

        Schema::create('withholding_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('withholding_code_id')->constrained('withholding_codes')->restrictOnDelete();
            $table->string('atc', 20);
            $table->date('transaction_date');
            $table->bigInteger('base');      // VAT-exclusive base
            $table->integer('rate_bp');
            $table->bigInteger('ewt');
            $table->timestamps();

            $table->index(['company_id', 'transaction_date']);
            $table->index(['company_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withholding_transactions');
        Schema::dropIfExists('bill_applications');
        Schema::dropIfExists('vendor_payments');
    }
};
