<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Orders had no numbers of their own (lists showed PO-{id}). Give every
     * company PO and SO sequences and number the orders it already has, in
     * the order they were created.
     */
    public function up(): void
    {
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ([['purchase_order', 'PO', 'purchase_orders'], ['sales_order', 'SO', 'sales_orders']] as [$key, $prefix, $table]) {
                $sequence = DB::table('document_sequences')->where('company_id', $companyId)->where('key', $key)->first();
                if ($sequence === null) {
                    DB::table('document_sequences')->insert([
                        'company_id' => $companyId, 'key' => $key, 'prefix' => $prefix, 'next_number' => 1, 'padding' => 6,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $sequence = DB::table('document_sequences')->where('company_id', $companyId)->where('key', $key)->first();
                }

                $next = (int) $sequence->next_number;
                $orders = DB::table($table)->where('company_id', $companyId)->whereNull('number')->orderBy('id')->get(['id', 'order_date']);
                foreach ($orders as $order) {
                    $year = (int) substr((string) $order->order_date, 0, 4);
                    $number = sprintf('%s-%d-%s', $sequence->prefix, $year, str_pad((string) $next, (int) $sequence->padding, '0', STR_PAD_LEFT));
                    DB::table($table)->where('id', $order->id)->update(['number' => $number]);
                    $next++;
                }
                DB::table('document_sequences')->where('id', $sequence->id)->update(['next_number' => $next]);
            }
        }
    }

    public function down(): void
    {
        // Numbers stay; they are harmless without the sequences.
    }
};
