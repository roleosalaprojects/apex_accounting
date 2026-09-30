<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Filament\Support\DocumentStatus;
use App\Models\CustomerPayment;
use App\Models\PaymentApplication;
use App\Services\Numbering\NumberGenerator;
use App\Support\AmountInWords;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Collection Receipt for a customer payment: the supplementary receipt
 * (§6.2) that goes with the Sales Invoice(s) it settles.
 */
final class PrintCollectionReceipt
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    public function render(CustomerPayment $payment): string
    {
        return Pdf::loadView('print.collection-receipt', $this->data($payment))->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(CustomerPayment $payment): array
    {
        $payment->loadMissing(['customer', 'company', 'preparedBy', 'applications.invoice']);

        // Collections made before receipts were numbered get theirs on first print.
        if ($payment->collection_receipt_no === null) {
            DB::transaction(function () use ($payment): void {
                $payment->forceFill([
                    'collection_receipt_no' => $this->numbers->next($payment->company_id, 'collection_receipt', $payment->payment_date->year),
                ])->save();
            });
        }

        return [
            'payment' => $payment,
            'company' => $payment->company,
            'method' => DocumentStatus::method($payment->method),
            'words' => AmountInWords::pesos($payment->amount->minor),
            'applications' => $payment->applications->map(function (PaymentApplication $application) use ($payment): array {
                $invoice = $application->invoice;
                $settledByThen = (int) DB::table('payment_applications')
                    ->join('customer_payments', 'customer_payments.id', '=', 'payment_applications.customer_payment_id')
                    ->where('payment_applications.invoice_id', $invoice->id)
                    ->where('customer_payments.status', 'posted')
                    ->where(fn ($q) => $q->whereDate('customer_payments.payment_date', '<', $payment->payment_date->toDateString())
                        ->orWhere(fn ($q) => $q->whereDate('customer_payments.payment_date', '=', $payment->payment_date->toDateString())
                            ->where('customer_payments.id', '<=', $payment->id)))
                    ->sum('payment_applications.amount');

                return [
                    'number' => $invoice->number ?? '—',
                    'date' => $invoice->invoice_date->format('M j, Y'),
                    'total' => $invoice->total->format(),
                    'applied' => $application->amount->format(),
                    'balance' => Money::of($invoice->total->minor - $settledByThen)->format(),
                ];
            })->all(),
        ];
    }
}
