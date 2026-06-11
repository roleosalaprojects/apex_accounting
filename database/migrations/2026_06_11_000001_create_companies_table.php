<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tin', 20)->nullable();
            $table->string('branch_code', 5)->default('00000');
            $table->text('address')->nullable();
            $table->enum('taxpayer_type', ['vat', 'non_vat'])->default('vat');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);
            $table->boolean('require_approval')->default(false);
            $table->boolean('block_negative_inventory')->default(true);
            $table->string('currency_code', 3)->default('PHP'); // reserved: multi-currency is v2
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
