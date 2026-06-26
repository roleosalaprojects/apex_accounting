<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Incoming POS Z-readings (§14): a staging inbox for end-of-day sales summaries
     * pushed by Apex POS. Nothing posts automatically — an admin selects which
     * readings to import, each becoming a DRAFT journal entry for final review.
     * This keeps the accounting system a separate, deliberate destination (much
     * like importing into any external accounting software).
     */
    public function up(): void
    {
        Schema::create('pos_z_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('business_date');
            $table->string('reference')->nullable();          // POS Z-reading no.
            $table->bigInteger('vatable_sales')->default(0);  // minor units
            $table->bigInteger('exempt_sales')->default(0);
            $table->bigInteger('zero_rated_sales')->default(0);
            $table->bigInteger('vat_amount')->default(0);
            $table->bigInteger('discounts')->default(0);
            $table->json('tenders')->nullable();              // {cash: n, card: n, ...}
            $table->string('status', 20)->default('pending'); // pending | imported | dismissed
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_z_readings');
    }
};
