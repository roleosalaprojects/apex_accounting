<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exchange rates (§17): functional-currency (PHP) units per 1 unit of a
     * foreign currency, effective on a date. Foreign documents convert at the
     * latest rate on or before the transaction date.
     */
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->date('rate_date');
            $table->decimal('rate', 18, 8); // PHP per 1 unit of currency_code
            $table->timestamps();

            $table->unique(['company_id', 'currency_code', 'rate_date']);
            $table->index(['company_id', 'currency_code', 'rate_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
