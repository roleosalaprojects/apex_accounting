<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting dimensions: department, project, fund, branch.
     * Present from day one to future-proof budgeting / fund accounting / branch
     * reporting without a ledger migration (v1 reporting is minimal). (§4.1)
     */
    public function up(): void
    {
        foreach (['departments', 'projects', 'funds', 'branches'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->softDeletes();
                $table->timestamps();

                $table->unique(['company_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        foreach (['branches', 'funds', 'projects', 'departments'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
