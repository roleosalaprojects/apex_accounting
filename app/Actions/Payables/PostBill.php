<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Payables\BillData;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\PricingMode;
use App\Exceptions\Ledger\InvalidVatBucketException;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Services\Numbering\NumberGenerator;
use App\Services\Tax\InputVatRouter;
use App\Services\Tax\TaxValidator;
use App\Services\Tax\VatMath;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Posts a vendor Bill with input VAT three-bucket attribution (§5.3, §7):
 *   Dr expense/asset (per line; exempt-bucket lines include their VAT in cost)
 *   Dr 1400 Input VAT            (direct_vatable VAT)
 *   Dr 1410 Deferred Common VAT  (common VAT)
 *      Cr 2100 AP (partner=vendor)
 */
final class PostBill
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly VatMath $vat,
        private readonly TaxValidator $taxValidator,
        private readonly InputVatRouter $router,
        private readonly NumberGenerator $numbers,
        private readonly InventoryService $inventory,
    ) {}

    public function handle(BillData $data, ?User $actor = null): Bill
    {
        return DB::transaction(function () use ($data, $actor): Bill {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            /** @var Vendor $vendor */
            $vendor = Vendor::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->vendor_id);

            if ($data->lines->count() === 0) {
                throw new RuntimeException('A bill needs at least one line.');
            }

            $taxCodes = TaxCode::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->get()->keyBy('id');

            $computed = $this->computeLines($company, $data, $taxCodes);

            $dueDate = $data->due_date
                ?? Carbon::parse($data->bill_date)->addDays($vendor->terms_days)->toDateString();

            $bill = new Bill;
            $bill->forceFill([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'bill_date' => $data->bill_date,
                'due_date' => $dueDate,
                'status' => InvoiceStatus::Posted,
                'pricing_mode' => $data->pricing_mode,
                'is_opening' => $data->is_opening,
                'vatable_purchases' => $computed['totals']['vatable'],
                'input_vat' => $computed['totals']['input_vat'],
                'exempt_purchases' => $computed['totals']['exempt'],
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
            $bill->number = $this->numbers->next($company->id, 'bill', Carbon::parse($data->bill_date)->year);
            $bill->save();

            foreach ($computed['lines'] as $line) {
                $bill->lines()->create($line['model']);
                $this->receiveInventory($company, $line);
            }

            $entry = $this->post->handle($this->buildJournalData($company, $vendor, $bill, $data, $computed), $actor);
            $bill->forceFill(['journal_entry_id' => $entry->id])->save();

            return $bill->load('lines');
        });
    }

    /**
     * @param  Collection<int, TaxCode>  $taxCodes
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    private function computeLines(Company $company, BillData $data, $taxCodes): array
    {
        $lines = [];
        $totals = ['vatable' => 0, 'input_vat' => 0, 'exempt' => 0, 'total' => 0];
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

            if ($vat > 0 && $lineData->vat_bucket === null) {
                throw InvalidVatBucketException::make("line {$lineNo} carries input VAT but no bucket");
            }

            $bucket = $lineData->vat_bucket;
            $capitalize = $bucket !== null && $this->router->isCapitalizedIntoCost($bucket) && $vat > 0;
            $costDebit = $capitalize ? $net + $vat : $net;
            $inputVatAccountCode = ($bucket !== null && $vat > 0) ? $this->router->accountCodeFor($bucket) : null;

            if ($taxCode->isVat12()) {
                $totals['vatable'] += $net;
            } elseif ($taxCode->isExempt()) {
                $totals['exempt'] += $net;
            } else {
                $totals['vatable'] += $net; // zero-rated treated as taxable purchase
            }
            if ($inputVatAccountCode !== null) {
                $totals['input_vat'] += $vat;
            }
            $totals['total'] += $net + $vat;

            $dims = [
                'department_id' => $lineData->department_id ?? $data->department_id,
                'project_id' => $lineData->project_id ?? $data->project_id,
                'fund_id' => $lineData->fund_id ?? $data->fund_id,
                'branch_id' => $lineData->branch_id ?? $data->branch_id,
            ];

            $lines[] = [
                'model' => array_merge([
                    'line_no' => $lineNo,
                    'item_id' => $lineData->item_id,
                    'description' => $lineData->description,
                    'qty' => $lineData->qty,
                    'unit_price' => $lineData->unit_price,
                    'tax_code_id' => $taxCode->id,
                    'vat_bucket' => $bucket,
                    'line_total' => $costDebit,
                    'vat_amount' => $vat,
                    'expense_or_asset_account_id' => $lineData->expense_or_asset_account_id,
                ], $dims),
                'cost_debit' => $costDebit,
                'vat' => $vat,
                'input_vat_account_code' => $inputVatAccountCode,
                'expense_account_id' => $lineData->expense_or_asset_account_id,
                'tax_code_id' => $taxCode->id,
                'bucket' => $bucket,
                'desc' => $lineData->description,
                'dims' => $dims,
            ];
            $lineNo++;
        }

        return ['lines' => $lines, 'totals' => $totals];
    }

    /**
     * @param  array{lines: array<int, array<string, mixed>>, totals: array<string, int>}  $computed
     */
    private function buildJournalData(Company $company, Vendor $vendor, Bill $bill, BillData $data, array $computed): JournalEntryData
    {
        $lines = [];

        if ($data->is_opening) {
            $lines[] = new JournalLineData(
                account_id: $this->account($company, '3950')->id,
                debit: $computed['totals']['total'],
                memo: 'Opening AP',
            );
        } else {
            foreach ($computed['lines'] as $line) {
                $lines[] = new JournalLineData(
                    account_id: $line['expense_account_id'],
                    debit: $line['cost_debit'],
                    memo: $line['desc'],
                    tax_code_id: $line['tax_code_id'],
                    vat_bucket: $line['bucket'],
                    department_id: $line['dims']['department_id'],
                    project_id: $line['dims']['project_id'],
                    fund_id: $line['dims']['fund_id'],
                    branch_id: $line['dims']['branch_id'],
                );

                if ($line['input_vat_account_code'] !== null) {
                    $lines[] = new JournalLineData(
                        account_id: $this->account($company, $line['input_vat_account_code'])->id,
                        debit: $line['vat'],
                        memo: 'Input VAT',
                        vat_bucket: $line['bucket'],
                    );
                }
            }
        }

        $lines[] = new JournalLineData(
            account_id: $this->account($company, '2100')->id,
            credit: $computed['totals']['total'],
            memo: 'AP — '.$vendor->name,
            partner_type: $vendor->getMorphClass(),
            partner_id: $vendor->id,
            department_id: $data->department_id,
            project_id: $data->project_id,
            fund_id: $data->fund_id,
            branch_id: $data->branch_id,
        );

        return new JournalEntryData(
            company_id: $company->id,
            entry_date: $data->bill_date,
            memo: 'Bill '.(string) $bill->number,
            lines: new DataCollection(JournalLineData::class, $lines),
            source_type: $bill->getMorphClass(),
            source_id: $bill->id,
            created_by: $data->created_by,
            approved_by: $data->approved_by ?? $data->created_by,
        );
    }

    /**
     * Receive stock into weighted-average valuation when a line targets an
     * inventory item (§9). The line cost (which already capitalizes exempt VAT)
     * is the receipt cost basis.
     *
     * @param  array<string, mixed>  $line
     */
    private function receiveInventory(Company $company, array $line): void
    {
        $itemId = $line['model']['item_id'] ?? null;
        if ($itemId === null) {
            return;
        }

        /** @var Item|null $item */
        $item = Item::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->find($itemId);

        if ($item === null || $item->type !== ItemType::Inventory) {
            return;
        }

        $this->inventory->receive(
            $item,
            Quantity::toUnits($line['model']['qty']),
            $line['cost_debit'],
        );
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
