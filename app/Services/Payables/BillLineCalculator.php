<?php

declare(strict_types=1);

namespace App\Services\Payables;

use App\Data\Payables\BillData;
use App\Data\Payables\BillLineData;
use App\Enums\ItemType;
use App\Enums\PricingMode;
use App\Exceptions\Ledger\InvalidVatBucketException;
use App\Exceptions\Ledger\InventoryAccountException;
use App\Models\Account;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxCode;
use App\Services\Tax\InputVatRouter;
use App\Services\Tax\TaxValidator;
use App\Services\Tax\VatMath;
use App\Support\Quantity;
use RuntimeException;

/**
 * The arithmetic of a bill (§5.3, §7): each line's cost, input VAT and
 * bucket, the account a stocked item must be costed to, and the totals —
 * shared by posting and by drafts awaiting approval.
 */
final class BillLineCalculator
{
    public function __construct(
        private readonly VatMath $vat,
        private readonly TaxValidator $taxValidator,
        private readonly InputVatRouter $router,
    ) {}

    /**
     * @return array{lines: array<int, array<string, mixed>>, totals: array<string, int>}
     */
    public function compute(Company $company, BillData $data): array
    {
        $taxCodes = TaxCode::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->get()->keyBy('id');
        $lines = [];
        $totals = ['vatable' => 0, 'input_vat' => 0, 'exempt' => 0, 'total' => 0];
        $lineNo = 1;

        $itemIds = [];
        foreach ($data->lines as $lineData) {
            if ($lineData->item_id !== null) {
                $itemIds[] = $lineData->item_id;
            }
        }
        $items = Item::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->whereIn('id', $itemIds)->get()->keyBy('id');

        foreach ($data->lines as $lineData) {
            $item = null;
            if ($lineData->item_id !== null) {
                $item = $items->get($lineData->item_id) ?? throw new RuntimeException("Item {$lineData->item_id} not found.");
            }
            $accountId = $this->costAccountFor($company, $lineNo, $lineData, $item);

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
                    'expense_or_asset_account_id' => $accountId,
                    'purchase_order_line_id' => $lineData->purchase_order_line_id,
                ], $dims),
                'item' => $item,
                'cost_debit' => $costDebit,
                'vat' => $vat,
                'input_vat_account_code' => $inputVatAccountCode,
                'expense_account_id' => $accountId,
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
     * The account a line's cost is debited to: the one on the line, except that
     * a stocked item may only go to its own inventory account.
     */
    private function costAccountFor(Company $company, int $lineNo, BillLineData $line, ?Item $item): int
    {
        if ($item === null || $item->type !== ItemType::Inventory) {
            return $line->expense_or_asset_account_id;
        }

        if ($item->inventory_account_id === null) {
            throw InventoryAccountException::missing($lineNo, $item->name);
        }

        if ($line->expense_or_asset_account_id !== $item->inventory_account_id) {
            $accounts = Account::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereIn('id', [$item->inventory_account_id, $line->expense_or_asset_account_id])
                ->get()->keyBy('id');
            $label = fn (int $id): string => ($a = $accounts->get($id)) !== null ? "{$a->code} {$a->name}" : "account #{$id}";

            throw InventoryAccountException::mismatch($lineNo, $item->name, $label($item->inventory_account_id), $label($line->expense_or_asset_account_id));
        }

        return $item->inventory_account_id;
    }
}
