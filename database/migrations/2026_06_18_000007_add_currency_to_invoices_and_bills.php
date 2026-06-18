<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign-currency annotation on AR/AP documents (§17). The ledger stays
     * functional (PHP); these record the transaction currency, the rate used to
     * convert to functional at issue, and the foreign-currency face total.
     */
    public function up(): void
    {
        foreach (['invoices', 'bills'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('currency_code', 3)->default('PHP');
                $table->decimal('exchange_rate', 18, 8)->default(1);
                $table->bigInteger('foreign_total')->nullable(); // foreign-currency face total, minor
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'bills'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropColumn(['currency_code', 'exchange_rate', 'foreign_total']);
            });
        }
    }
};
