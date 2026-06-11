<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['journal_entry', 'invoice', 'bill', 'depreciation_run']);
            $table->json('payload')->nullable(); // the document DTO to instantiate
            $table->enum('schedule', ['monthly', 'quarterly', 'annually'])->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on');
            $table->boolean('auto_post')->default(false); // false -> drafts; true -> post (accountant-gated)
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'next_run_on']);
        });

        Schema::create('recurring_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recurring_template_id')->constrained()->cascadeOnDelete();
            $table->date('ran_on');
            $table->string('created_document_type')->nullable();
            $table->unsignedBigInteger('created_document_id')->nullable();
            $table->enum('status', ['created', 'posted', 'failed'])->default('created');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['recurring_template_id', 'ran_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_runs');
        Schema::dropIfExists('recurring_templates');
    }
};
