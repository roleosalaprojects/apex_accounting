<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Year-end closing entries move the year's result to retained earnings;
     * they are not trading and must stay out of income statements. Flag them
     * so reports can tell.
     */
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->boolean('is_closing')->default(false)->after('status');
        });
        DB::table('journal_entries')->where('memo', 'like', 'Year-end closing entry%')->update(['is_closing' => true]);
    }

    public function down(): void
    {
        Schema::table('journal_entries', fn (Blueprint $table) => $table->dropColumn('is_closing'));
    }
};
