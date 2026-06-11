<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Payables\PayBillData;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\WithholdingCode;
use App\Services\Numbering\NumberGenerator;
use App\Services\Payables\BillStatusRecalculator;
use App\Services\Tax\VatMath;
use App\Services\Tax\WithholdingMath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Pays one or more bills, computing EWT on the VAT-exclusive base (§7):
 *   Dr 2100 AP (partner=vendor)   gross applied
 *      Cr 2210 EWT Payable        ewt (rate from withholding code)
 *      Cr cash/bank               net paid
 *
 * Persists a withholding_transactions row — the 2307 / 0619-E / 1601-EQ source.
 */
final class PayBill
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly NumberGenerator $numbers,
        private readonly WithholdingMath $withholdingMath,
        private readonly VatMath $vat,
        private readonly BillStatusRecalculator $recalculator,
    ) {}

    public function handle(PayBillData $data, ?User $actor = null): VendorPayment
    {
        return DB::transaction(function () use ($data, $actor): VendorPayment {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            /** @var Vendor $vendor */
            $vendor = Vendor::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->vendor_id);

            $withholdingCode = $this->resolveWithholdingCode($company, $vendor, $data);

            $grossApplied = 0;
            $ewtBase = 0;
            $affectedBills = [];

            foreach ($data->applications as $application) {
                /** @var Bill $bill */
                $bill = Bill::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)->findOrFail($application->bill_id);

                if ($application->amount > $bill->outstanding()) {
                    throw new RuntimeException("Application exceeds bill {$bill->number} outstanding.");
                }

                $grossApplied += $application->amount;

                // VAT-exclusive base for EWT, proportional to the amount being settled.
                $billNet = $bill->vatable_purchases->minor + $bill->exempt_purchases->minor;
                $billTotal = $bill->total->minor;
                $ewtBase += $billTotal > 0
                    ? $this->vat->roundDiv($application->amount * $billNet, $billTotal)
                    : 0;

                $affectedBills[] = ['bill' => $bill, 'amount' => $application->amount];
            }

            $ewt = $withholdingCode !== null
                ? $this->withholdingMath->compute($ewtBase, $withholdingCode->rate_bp)
                : 0;
            $netPaid = $grossApplied - $ewt;

            $payment = new VendorPayment;
            $payment->forceFill([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'payment_date' => $data->payment_date,
                'method' => $data->method,
                'paid_from_account_id' => $data->paid_from_account_id,
                'gross_applied' => $grossApplied,
                'ewt' => $ewt,
                'net_paid' => $netPaid,
                'status' => 'posted',
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'created_by' => $data->created_by ?? $actor?->id,
                'approved_by' => $data->approved_by ?? $actor?->id,
                'approved_at' => now(),
            ]);
            $year = Carbon::parse($data->payment_date)->year;
            $payment->number = $this->numbers->next($company->id, 'payment_out', $year);
            $payment->voucher_no = $this->numbers->next($company->id, 'payment_voucher', $year);
            $payment->save();

            foreach ($affectedBills as $entry) {
                $payment->applications()->create([
                    'bill_id' => $entry['bill']->id,
                    'amount' => $entry['amount'],
                ]);
            }

            if ($withholdingCode !== null && $ewt > 0) {
                $payment->withholdingTransactions()->create([
                    'company_id' => $company->id,
                    'vendor_id' => $vendor->id,
                    'withholding_code_id' => $withholdingCode->id,
                    'atc' => $withholdingCode->atc,
                    'transaction_date' => $data->payment_date,
                    'base' => $ewtBase,
                    'rate_bp' => $withholdingCode->rate_bp,
                    'ewt' => $ewt,
                ]);
            }

            $journalEntry = $this->post->handle($this->buildJournalData($company, $vendor, $payment, $data, $grossApplied, $ewt, $netPaid), $actor);
            $payment->forceFill(['journal_entry_id' => $journalEntry->id])->save();

            foreach ($affectedBills as $entry) {
                $this->recalculator->recalculate($entry['bill']->fresh());
            }

            return $payment->load('applications', 'withholdingTransactions');
        });
    }

    private function resolveWithholdingCode(Company $company, Vendor $vendor, PayBillData $data): ?WithholdingCode
    {
        $id = $data->withholding_code_id ?? $vendor->default_withholding_code_id;
        if ($id === null) {
            return null;
        }

        return WithholdingCode::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->find($id);
    }

    private function buildJournalData(Company $company, Vendor $vendor, VendorPayment $payment, PayBillData $data, int $gross, int $ewt, int $net): JournalEntryData
    {
        $lines = [];
        $lines[] = new JournalLineData(
            account_id: $this->account($company, '2100')->id,
            debit: $gross,
            memo: 'AP settled — '.$vendor->name,
            partner_type: $vendor->getMorphClass(),
            partner_id: $vendor->id,
        );

        if ($ewt > 0) {
            $lines[] = new JournalLineData(
                account_id: $this->account($company, '2210')->id,
                credit: $ewt,
                memo: 'EWT payable',
            );
        }

        $lines[] = new JournalLineData(
            account_id: $data->paid_from_account_id,
            credit: $net,
            memo: 'Payment to '.$vendor->name,
        );

        return new JournalEntryData(
            company_id: $company->id,
            entry_date: $data->payment_date,
            memo: 'Vendor payment '.(string) $payment->number,
            lines: new DataCollection(JournalLineData::class, $lines),
            source_type: $payment->getMorphClass(),
            source_id: $payment->id,
            created_by: $data->created_by,
            approved_by: $data->approved_by ?? $data->created_by,
        );
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
