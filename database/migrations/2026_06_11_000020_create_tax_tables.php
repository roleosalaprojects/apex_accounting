<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);          // VAT12, EXEMPT, ZERO
            $table->string('name');
            $table->integer('rate_bp');          // basis points: 1200 / 0 / 0
            $table->enum('kind', ['output', 'input_capable'])->default('output');
            $table->foreignId('sales_account_hint')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('withholding_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);          // e.g. WC158
            $table->string('name');              // e.g. EWT — Goods 1%
            $table->integer('rate_bp');          // 100 = 1%
            $table->string('atc', 20);           // BIR ATC string
            $table->enum('applies_to', ['purchase', 'sale'])->default('purchase');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('vat_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('fiscal_year');
            $table->unsignedTinyInteger('quarter'); // 1..4
            $table->bigInteger('vatable_sales');    // net of VAT
            $table->bigInteger('exempt_sales');
            $table->bigInteger('common_input_vat');
            $table->integer('ratio_creditable_bp'); // basis points
            $table->bigInteger('creditable');
            $table->bigInteger('non_creditable');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_allocations');
        Schema::dropIfExists('withholding_codes');
        Schema::dropIfExists('tax_codes');
    }
};
