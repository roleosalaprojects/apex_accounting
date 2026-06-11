<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Receivables\CreditMemoData;
use App\Enums\PricingMode;
use App\Models\Account;
use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\Customer;
use App\Models\TaxCode;
use App\Models\User;
use App\Services\Numbering\NumberGenerator;
use App\Services\Tax\TaxValidator;
use App\Services\Tax\VatMath;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Posts a sales credit memo — the mirror of an invoice (§6.1). Output VAT on a
 * credit memo posts as a DEBIT to 2200 so sales returns net correctly inside
 * the same quarter's 2550Q (§6.1):
 *   Dr income (per line, net)
 *   Dr 2200 Output VAT             vat
 *      Cr 1200 AR (partner=customer) total
 */
final class PostCreditMemo
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly VatMath $vat,
        private readonly TaxValidator $taxValidator,
        private readonly NumberGenerator $numbers,
    ) {}

    public function handle(CreditMemoData $data, ?User $actor = null): CreditMemo
    {
        return DB::transaction(function () use ($data, $actor): CreditMemo {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            /** @var Customer $customer */
            $customer = Customer::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->customer_id);

            if ($data->lines->count() === 0) {
                throw new RuntimeException('A credit memo needs at least one line.');
            }

            $taxCodes = TaxCode::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->get()->keyBy('id');

            $totals = ['vatable' => 0, 'vat' => 0, 'exempt' => 0, 'zero' => 0, 'total' => 0];
            $lineModels = [];
            $incomeLines = [];
            $lineNo = 1;

            foreach ($data->lines as $lineData) {
                /** @var TaxCode|null $taxCode */
                $taxCode = $taxCodes->get($lineData->tax_code_id);
                if ($taxCode === null) {
                    throw new RuntimeException("Tax code {$lineData->tax_code_id} not found.");
                }
                $this->taxValidator->assertAllowed($taxCode, $company->taxpayer_type);

                $gross = Quantity::extend($lineData->unit_price, Quantity::toUnits($lineData->qty));
                $breakdown = $data->pricing_mode === PricingMode::VatInclusive
                    ? $this->vat->fromInclusive($gross, $taxCode->rate_bp)
                    : $this->vat->fromExclusive($gross, $taxCode->rate_bp);

                $net = $breakdown->base;
                $vat = $breakdown->vat;

                if ($taxCode->isExempt()) {
                    $totals['exempt'] += $net;
                } elseif ($taxCode->isZeroRated()) {
                    $totals['zero'] += $net;
                } else {
                    $totals['vatable'] += $net;
                }
                $totals['vat'] += $vat;
                $totals['total'] += $net + $vat;

                $lineModels[] = [
                    'line_no' => $lineNo++,
                    'item_id' => $lineData->item_id,
                    'description' => $lineData->description,
                    'qty' => $lineData->qty,
                    'unit_price' => $lineData->unit_price,
                    'tax_code_id' => $taxCode->id,
                    'line_total' => $net,
                    'vat_amount' => $vat,
                    'income_account_id' => $lineData->income_account_id,
                ];
                $incomeLines[] = ['account_id' => $lineData->income_account_id, 'net' => $net, 'tax_code_id' => $taxCode->id, 'desc' => $lineData->description];
            }

            $memo = new CreditMemo;
            $memo->forceFill([
                'company_id' => $company->id,
                'customer_id' => $customer->id,
                'memo_date' => $data->memo_date,
                'status' => 'posted',
                'pricing_mode' => $data->pricing_mode,
                'vatable_sales' => $totals['vatable'],
                'vat_amount' => $totals['vat'],
                'exempt_sales' => $totals['exempt'],
                'zero_rated_sales' => $totals['zero'],
                'total' => $totals['total'],
                'memo' => $data->memo,
                'created_by' => $data->created_by ?? $actor?->id,
                'approved_by' => $data->approved_by ?? $actor?->id,
                'approved_at' => now(),
            ]);
            $memo->number = $this->numbers->next($company->id, 'credit_memo', Carbon::parse($data->memo_date)->year);
            $memo->save();

            foreach ($lineModels as $model) {
                $memo->lines()->create($model);
            }

            $jeLines = [];
            foreach ($incomeLines as $line) {
                $jeLines[] = new JournalLineData(
                    account_id: $line['account_id'],
                    debit: $line['net'],
                    memo: 'Sales return — '.$line['desc'],
                    tax_code_id: $line['tax_code_id'],
                );
            }
            if ($totals['vat'] > 0) {
                $jeLines[] = new JournalLineData(
                    account_id: $this->account($company, '2200')->id,
                    debit: $totals['vat'],
                    memo: 'Output VAT reversal',
                );
            }
            $jeLines[] = new JournalLineData(
                account_id: $this->account($company, '1200')->id,
                credit: $totals['total'],
                memo: 'AR credit — '.$customer->name,
                partner_type: $customer->getMorphClass(),
                partner_id: $customer->id,
            );

            $entry = $this->post->handle(new JournalEntryData(
                company_id: $company->id,
                entry_date: $data->memo_date,
                memo: 'Credit memo '.(string) $memo->number,
                lines: new DataCollection(JournalLineData::class, $jeLines),
                source_type: $memo->getMorphClass(),
                source_id: $memo->id,
                created_by: $data->created_by,
                approved_by: $data->approved_by ?? $data->created_by,
            ), $actor);

            $memo->forceFill(['journal_entry_id' => $entry->id])->save();

            return $memo->load('lines');
        });
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
