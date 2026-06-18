<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persisted BIR return filings (§12): a point-in-time snapshot of the figures
     * for a period, kept for audit and the 10-year retention requirement.
     */
    public function up(): void
    {
        Schema::create('tax_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                 // 2550Q | 1601EQ
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedTinyInteger('quarter')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('figures');                    // snapshot of the computed figures
            $table->string('status', 20)->default('draft'); // draft | filed
            $table->string('reference_no')->nullable(); // BIR confirmation / eFPS ref
            $table->timestamp('filed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'type', 'fiscal_year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_returns');
    }
};
