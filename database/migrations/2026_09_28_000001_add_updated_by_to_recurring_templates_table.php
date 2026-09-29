<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring runs post under the authority of whoever last saved the template
 * (RunDueTemplates). Existing templates start with no editor on purpose: they
 * keep creating drafts, but must be re-saved by someone with posting rights
 * before they post again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recurring_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
        });
    }
};
