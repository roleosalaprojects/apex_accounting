<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Models\CreditMemo;
use App\Models\Invoice;
use App\Services\Receivables\InvoiceStatusRecalculator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a posted credit memo to one or more invoices (no GL effect — the GL
 * moved at PostCreditMemo; this links the credit at subledger level and rolls
 * the invoice status).
 */
final class ApplyCreditMemo
{
    public function __construct(private readonly InvoiceStatusRecalculator $recalculator) {}

    /**
     * @param  array<int, array{invoice_id: int, amount: int}>  $applications
     */
    public function handle(CreditMemo $memo, array $applications): CreditMemo
    {
        return DB::transaction(function () use ($memo, $applications): CreditMemo {
            $available = $memo->total->minor - (int) $memo->applications()->sum('amount');

            foreach ($applications as $application) {
                $amount = $application['amount'];
                if ($amount > $available) {
                    throw new RuntimeException('Credit memo application exceeds available credit.');
                }

                /** @var Invoice $invoice */
                $invoice = Invoice::query()->withoutGlobalScopes()
                    ->where('company_id', $memo->company_id)
                    ->findOrFail($application['invoice_id']);

                if ($amount > $invoice->outstanding()) {
                    throw new RuntimeException("Application exceeds invoice {$invoice->number} outstanding.");
                }

                $memo->applications()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                ]);
                $available -= $amount;

                $this->recalculator->recalculate($invoice->fresh());
            }

            $memo->forceFill(['status' => 'applied'])->save();

            return $memo;
        });
    }
}
