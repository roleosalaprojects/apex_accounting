<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A disposed asset remembers what it fetched and the gain or loss, for the register. */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->bigInteger('disposal_proceeds')->nullable()->after('disposed_at');
            $table->bigInteger('disposal_gain_loss')->nullable()->after('disposal_proceeds');
        });

        foreach (DB::table('assets')->where('status', 'disposed')->get(['id', 'company_id']) as $asset) {
            $entry = DB::table('journal_entries')->where('company_id', $asset->company_id)
                ->where('source_type', 'App\\Models\\Asset')->where('source_id', $asset->id)
                ->whereNull('reversal_of_id')->orderByDesc('id')->first(['id']);
            if ($entry === null) {
                continue;
            }
            $proceeds = (int) DB::table('journal_lines')->where('journal_entry_id', $entry->id)->where('memo', 'Disposal proceeds')->sum('debit');
            $gain = DB::table('journal_lines')->where('journal_entry_id', $entry->id)->whereIn('memo', ['Gain on disposal', 'Loss on disposal'])
                ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as gain')->value('gain');
            DB::table('assets')->where('id', $asset->id)->update(['disposal_proceeds' => $proceeds, 'disposal_gain_loss' => (int) $gain]);
        }
    }

    public function down(): void
    {
        Schema::table('assets', fn (Blueprint $table) => $table->dropColumn(['disposal_proceeds', 'disposal_gain_loss']));
    }
};
