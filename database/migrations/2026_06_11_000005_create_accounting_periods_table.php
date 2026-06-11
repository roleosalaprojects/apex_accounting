<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('fiscal_year');
            $table->unsignedTinyInteger('period_no'); // 1..12
            $table->date('starts_on');
            $table->date('ends_on');
            // open = postable; closed = re-openable by admin; locked = year-end, never reopens
            $table->enum('status', ['open', 'closed', 'locked'])->default('open');
            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year', 'period_no']);
            $table->index(['company_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
