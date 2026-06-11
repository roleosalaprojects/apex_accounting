<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Receivables\InvoiceData;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Account;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\TaxCode;
use App\Models\User;
use App\Services\Numbering\NumberGenerator;
use App\Services\Tax\TaxValidator;
use App\Services\Tax\VatMath;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Posts a sales Invoice (§6.2). Segregates VATable / exempt / zero-rated at the
 * line level (§16.4) and posts:
 *   Dr 1200 AR (partner=customer)            total
 *      Cr income (per line, net of VAT)      line totals
 *      Cr 2200 Output VAT                    vat_amount
 *
 * Opening invoices (is_opening) post Dr AR / Cr 3950 Opening Balance Equity so
 * aging works without double-counting revenue (§4.1b).
 */
final class PostInvoice
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly VatMath $vat,
        private readonly TaxValidator $taxValidator,
        private readonly NumberGenerator $numbers,
    ) {}

    public function handle(InvoiceData $data, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($data, $actor): Invoice {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);

            /** @var Customer $customer */
            $customer = Customer::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->customer_id);

            if ($data->lines->count() === 0) {
                throw new RuntimeException('An invoice needs at least one line.');
            }

            $taxCodes = TaxCode::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->get()->keyBy('id');

            $computed = $this->computeLines($company, $data, $taxCodes);

            $dueDate = $data->due_date
                ?? Carbon::parse($data->invoice_date)->addDays($customer->terms_days)->toDateString();

            $invoice = new Invoice;
            $invoice->forceFill([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'invoice_date' => $data->invoice_date,
                'due_date' => $dueDate,
                'status' => InvoiceStatus::Posted,
                'pricing_mode' => $data->pricing_mode,
                'is_opening' => $data->is_opening,
                'vatable_sales' => $computed['totals']['vatable'],
                'vat_amount' => $computed['totals']['vat'],
                'exempt_sales' => $computed['totals']['exempt'],
                'zero_rated_sales' => $computed['totals']['zero'],
                'total' => $computed['totals']['total'],
                'memo' => $data->memo,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'remarks' => $data->remarks,
                'created_by' => $data->created_by ?? $actor?->id,
                'approved_by' => $data->approved_by ?? $actor?->id,
                'approved_at' => now(),
                'department_id' => $data->department_id,
                'project_id' => $data->project_id,
                'fund_id' => $data->fund_id,
                'branch_id' => $data->branch_id,
            ]);
            $invoice->number = $this->numbers->next($company->id, 'invoice', Carbon::parse($data->invoice_date)->year);
            $invoice->save();

            foreach ($computed['lines'] as $line) {
                $invoice->lines()->create($line['model']);
            }

            $journalData = $this->buildJournalData($company, $customer, $invoice, $data, $computed);
            $entry = $this->post->handle($journalData, $actor);

            $invoice->forceFill(['journal_entry_id' => $entry->id])->save();

            return $invoice->load('lines');
        });
    }

    /**
     * @param  Collection<int, TaxCode>  $taxCodes
     * @return array{lines: array<int, array{model: array<string, mixed>, net: int, vat: int, income_account_id: int, tax_code_id: int, dims: array<string, int|null>}>, totals: array{vatable: int, vat: int, exempt: int, zero: int, total: int}}
     */
    private function computeLines(Company $company, InvoiceData $data, $taxCodes): array
    {
        $lines = [];
        $totals = ['vatable' => 0, 'vat' => 0, 'exempt' => 0, 'zero' => 0, 'total' => 0];
        $lineNo = 1;

        foreach ($data->lines as $lineData) {
            /** @var TaxCode|null $taxCode */
            $taxCode = $taxCodes->get($lineData->tax_code_id);
            if ($taxCode === null) {
                throw new RuntimeException("Tax code {$lineData->tax_code_id} not found.");
            }

            $this->taxValidator->assertAllowed($taxCode, $company->taxpayer_type);

            $units = Quantity::toUnits($lineData->qty);
            $gross = Quantity::extend($lineData->unit_price, $units);

            $breakdown = $data->pricing_mode === PricingMode::VatInclusive
                ? $this->vat->fromInclusive($gross, $taxCode->rate_bp)
                : $this->vat->fromExclusive($gross, $taxCode->rate_bp);

            $net = $breakdown->base;
            $vat = $breakdown->vat;
            $lineTotal = $net + $vat;

            if ($taxCode->isExempt()) {
                $totals['exempt'] += $net;
            } elseif ($taxCode->isZeroRated()) {
                $totals['zero'] += $net;
            } else {
                $totals['vatable'] += $net;
            }
            $totals['vat'] += $vat;
            $totals['total'] += $lineTotal;

            $dims = [
                'department_id' => $lineData->department_id ?? $data->department_id,
                'project_id' => $lineData->project_id ?? $data->project_id,
                'fund_id' => $lineData->fund_id ?? $data->fund_id,
                'branch_id' => $lineData->branch_id ?? $data->branch_id,
            ];

            $lines[] = [
                'model' => array_merge([
                    'line_no' => $lineNo++,
                    'item_id' => $lineData->item_id,
                    'description' => $lineData->description,
                    'qty' => $lineData->qty,
                    'unit_price' => $lineData->unit_price,
                    'tax_code_id' => $taxCode->id,
                    'line_total' => $net,
                    'vat_amount' => $vat,
                    'income_account_id' => $lineData->income_account_id,
                ], $dims),
                'net' => $net,
                'vat' => $vat,
                'income_account_id' => $lineData->income_account_id,
                'tax_code_id' => $taxCode->id,
                'dims' => $dims,
            ];
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    /**
     * @param  array{lines: array<int, array<string, mixed>>, totals: array<string, int>}  $computed
     */
    private function buildJournalData(Company $company, Customer $customer, Invoice $invoice, InvoiceData $data, array $computed): JournalEntryData
    {
        $defaultDims = [
            'department_id' => $data->department_id,
            'project_id' => $data->project_id,
            'fund_id' => $data->fund_id,
            'branch_id' => $data->branch_id,
        ];

        $arAccount = $this->account($company, '1200');

        $lines = [];
        // Dr AR control with partner.
        $lines[] = new JournalLineData(
            account_id: $arAccount->id,
            debit: $computed['totals']['total'],
            memo: 'AR — '.$customer->name,
            partner_type: $customer->getMorphClass(),
            partner_id: $customer->id,
            department_id: $defaultDims['department_id'],
            project_id: $defaultDims['project_id'],
            fund_id: $defaultDims['fund_id'],
            branch_id: $defaultDims['branch_id'],
        );

        if ($data->is_opening) {
            // Offset to Opening Balance Equity instead of income/VAT.
            $lines[] = new JournalLineData(
                account_id: $this->account($company, '3950')->id,
                credit: $computed['totals']['total'],
                memo: 'Opening AR',
            );

            return $this->wrap($company, $invoice, $data, $lines);
        }

        foreach ($computed['lines'] as $line) {
            $lines[] = new JournalLineData(
                account_id: $line['income_account_id'],
                credit: $line['net'],
                memo: $line['model']['description'],
                tax_code_id: $line['tax_code_id'],
                department_id: $line['dims']['department_id'],
                project_id: $line['dims']['project_id'],
                fund_id: $line['dims']['fund_id'],
                branch_id: $line['dims']['branch_id'],
            );
        }

        if ($computed['totals']['vat'] > 0) {
            $lines[] = new JournalLineData(
                account_id: $this->account($company, '2200')->id,
                credit: $computed['totals']['vat'],
                memo: 'Output VAT',
            );
        }

        return $this->wrap($company, $invoice, $data, $lines);
    }

    /**
     * @param  array<int, JournalLineData>  $lines
     */
    private function wrap(Company $company, Invoice $invoice, InvoiceData $data, array $lines): JournalEntryData
    {
        return new JournalEntryData(
            company_id: $company->id,
            entry_date: $data->invoice_date,
            memo: 'Invoice '.(string) $invoice->number,
            lines: new DataCollection(JournalLineData::class, $lines),
            source_type: $invoice->getMorphClass(),
            source_id: $invoice->id,
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
