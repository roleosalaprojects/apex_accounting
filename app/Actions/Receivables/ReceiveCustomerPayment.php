<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Receivables\CustomerPaymentData;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Numbering\NumberGenerator;
use App\Services\Receivables\InvoiceStatusRecalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Receives a customer payment and applies it to invoices (§6.2):
 *   Dr cash/bank                      amount
 *   Dr 1450 Creditable Withholding    ewt_withheld
 *      Cr 1200 AR (partner=customer)  amount + ewt_withheld
 */
final class ReceiveCustomerPayment
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly NumberGenerator $numbers,
        private readonly InvoiceStatusRecalculator $recalculator,
    ) {}

    public function handle(CustomerPaymentData $data, ?User $actor = null): CustomerPayment
    {
        return DB::transaction(function () use ($data, $actor): CustomerPayment {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);

            /** @var Customer $customer */
            $customer = Customer::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->customer_id);

            $grossApplied = 0;
            foreach ($data->applications as $application) {
                $grossApplied += $application->amount;
            }
            if ($grossApplied !== $data->amount + $data->ewt_withheld) {
                throw new RuntimeException('Applications must total cash amount + EWT withheld.');
            }

            $deposit = Account::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->deposit_to_account_id);

            $payment = new CustomerPayment;
            $payment->forceFill([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'payment_date' => $data->payment_date,
                'method' => $data->method,
                'deposit_to_account_id' => $deposit->id,
                'amount' => $data->amount,
                'ewt_withheld' => $data->ewt_withheld,
                'status' => 'posted',
                'collection_receipt_no' => $data->collection_receipt_no,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'created_by' => $data->created_by ?? $actor?->id,
                'approved_by' => $data->approved_by ?? $actor?->id,
                'approved_at' => now(),
            ]);
            $payment->number = $this->numbers->next($company->id, 'payment_in', Carbon::parse($data->payment_date)->year);
            $payment->save();

            $affectedInvoices = [];
            foreach ($data->applications as $application) {
                /** @var Invoice $invoice */
                $invoice = Invoice::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)->findOrFail($application->invoice_id);

                if ($application->amount > $invoice->outstanding()) {
                    throw new RuntimeException("Application exceeds invoice {$invoice->number} outstanding.");
                }

                $payment->applications()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $application->amount,
                ]);
                $affectedInvoices[] = $invoice;
            }

            $entry = $this->post->handle($this->buildJournalData($company, $customer, $payment, $data), $actor);
            $payment->forceFill(['journal_entry_id' => $entry->id])->save();

            foreach ($affectedInvoices as $invoice) {
                $this->recalculator->recalculate($invoice->fresh());
            }

            return $payment->load('applications');
        });
    }

    private function buildJournalData(Company $company, Customer $customer, CustomerPayment $payment, CustomerPaymentData $data): JournalEntryData
    {
        $lines = [];
        $lines[] = new JournalLineData(
            account_id: $data->deposit_to_account_id,
            debit: $data->amount,
            memo: 'Collection — '.$customer->name,
        );

        if ($data->ewt_withheld > 0) {
            $lines[] = new JournalLineData(
                account_id: $this->account($company, '1450')->id,
                debit: $data->ewt_withheld,
                memo: 'Creditable withholding tax (2307)',
            );
        }

        $lines[] = new JournalLineData(
            account_id: $this->account($company, '1200')->id,
            credit: $data->amount + $data->ewt_withheld,
            memo: 'AR settled — '.$customer->name,
            partner_type: $customer->getMorphClass(),
            partner_id: $customer->id,
        );

        return new JournalEntryData(
            company_id: $company->id,
            entry_date: $data->payment_date,
            memo: 'Customer payment '.(string) $payment->number,
            lines: new DataCollection(JournalLineData::class, $lines),
            source_type: $payment->getMorphClass(),
            source_id: $payment->id,
            created_by: $data->created_by,
            approved_by: $data->approved_by ?? $data->created_by,
        );
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->firstOrFail();
    }
}
