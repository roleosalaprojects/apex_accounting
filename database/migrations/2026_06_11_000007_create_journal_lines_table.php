<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('line_no');
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();

            // Exactly one of debit/credit is non-zero per line (centavos, >= 0).
            $table->bigInteger('debit')->default(0);
            $table->bigInteger('credit')->default(0);
            $table->text('memo')->nullable();

            // Partner (customer/vendor) — required when account is AR/AP control.
            $table->string('partner_type')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();

            // Tax context (§5)
            $table->foreignId('tax_code_id')->nullable();
            $table->enum('vat_bucket', ['direct_vatable', 'direct_exempt', 'common'])->nullable();

            // Accounting dimensions — carried down from document lines where set.
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id']);
            $table->index(['partner_type', 'partner_id']);
            $table->index('department_id');
            $table->index('project_id');
            $table->index('fund_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
