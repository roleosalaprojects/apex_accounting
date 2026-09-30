<?php

declare(strict_types=1);

namespace App\Services\Printing;

use App\Filament\Support\DocumentStatus;
use App\Models\Account;
use App\Models\BillApplication;
use App\Models\VendorPayment;
use App\Models\WithholdingTransaction;
use App\Support\AmountInWords;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/** Payment (check) Voucher for a vendor payment: what was paid, on which bills, less EWT. */
final class PrintPaymentVoucher
{
    public function render(VendorPayment $payment): string
    {
        return Pdf::loadView('print.payment-voucher', $this->data($payment))->output();
    }

    /**
     * @return array<string, mixed>
     */
    public function data(VendorPayment $payment): array
    {
        $payment->loadMissing(['vendor', 'company', 'preparedBy', 'checkedBy', 'approvedBy', 'journalEntry', 'applications.bill', 'withholdingTransactions']);
        $account = Account::query()->withoutGlobalScopes()->find($payment->paid_from_account_id);

        return [
            'payment' => $payment,
            'company' => $payment->company,
            'method' => DocumentStatus::method($payment->method),
            'account' => $account === null ? '—' : "{$account->code} {$account->name}",
            'words' => AmountInWords::pesos($payment->net_paid->minor),
            'applications' => $payment->applications->map(function (BillApplication $application) use ($payment): array {
                $bill = $application->bill;
                $settledByThen = (int) DB::table('bill_applications')
                    ->join('vendor_payments', 'vendor_payments.id', '=', 'bill_applications.vendor_payment_id')
                    ->where('bill_applications.bill_id', $bill->id)
                    ->where('vendor_payments.status', 'posted')
                    ->where(fn ($q) => $q->whereDate('vendor_payments.payment_date', '<', $payment->payment_date->toDateString())
                        ->orWhere(fn ($q) => $q->whereDate('vendor_payments.payment_date', '=', $payment->payment_date->toDateString())
                            ->where('vendor_payments.id', '<=', $payment->id)))
                    ->sum('bill_applications.amount');

                return [
                    'number' => $bill->number ?? '—',
                    'date' => $bill->bill_date->format('M j, Y'),
                    'total' => $bill->total->format(),
                    'applied' => $application->amount->format(),
                    'balance' => Money::of($bill->total->minor - $settledByThen)->format(),
                ];
            })->all(),
            'withholdings' => $payment->withholdingTransactions->map(fn (WithholdingTransaction $tx): array => [
                'atc' => $tx->atc,
                'rate' => number_format($tx->rate_bp / 100, 2).'%',
                'base' => $tx->base->format(),
                'ewt' => $tx->ewt->format(),
            ])->all(),
        ];
    }
}
