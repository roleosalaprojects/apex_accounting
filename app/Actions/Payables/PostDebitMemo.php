<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Payables\BillLineData;
use App\Data\Payables\DebitMemoData;
use App\Enums\ItemType;
use App\Enums\PricingMode;
use App\Enums\StockMovementKind;
use App\Exceptions\Ledger\InvalidVatBucketException;
use App\Exceptions\Ledger\InventoryAccountException;
use App\Models\Account;
use App\Models\Company;
use App\Models\DebitMemo;
use App\Models\Item;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\Movement;
use App\Services\Numbering\NumberGenerator;
use App\Services\Tax\InputVatRouter;
use App\Services\Tax\TaxValidator;
use App\Services\Tax\VatMath;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Posts a vendor debit memo — the mirror of a bill (§7): goods sent back or a
 * credit the vendor granted. It reduces what we owe and unwinds the cost and
 * input VAT the bill booked, bucket for bucket:
 *   Dr 2100 AP (partner=vendor)        total
 *      Cr expense/asset (per line)     net (exempt-bucket lines include their VAT)
 *      Cr 1400 / 1410 Input VAT        vat, by bucket
 *
 * Returned stock leaves weighted-average inventory at the running average;
 * when the vendor credits more or less than that, the difference is a cost of
 * sales gain or loss, so the stock subledger stays equal to the ledger.
 */
final class PostDebitMemo
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly VatMath $vat,
        private readonly TaxValidator $taxValidator,
        private readonly InputVatRouter $router,
        private readonly NumberGenerator $numbers,
        private readonly InventoryService $inventory,
    ) {}

    public function handle(DebitMemoData $data, ?User $actor = null): DebitMemo
    {
        return DB::transaction(function () use ($data, $actor): DebitMemo {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            /** @var Vendor $vendor */
            $vendor = Vendor::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->vendor_id);

            if ($data->lines->count() === 0) {
                throw new RuntimeException('A debit memo needs at least one line.');
            }

            $taxCodes = TaxCode::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->get()->keyBy('id');
            $items = Item::query()->withoutGlobalScopes()->where('company_id', $company->id)
                ->whereIn('id', array_filter(array_map(fn (BillLineData $l): ?int => $l->item_id, $data->lines->all())))
                ->get()->keyBy('id');

            $totals = ['vatable' => 0, 'input_vat' => 0, 'exempt' => 0, 'total' => 0];
            $lines = [];
            $lineNo = 1;

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
                $capitalized = $bucket !== null && $vat > 0 && $this->router->isCapitalizedIntoCost($bucket);
                $costCredit = $capitalized ? $net + $vat : $net;
                $inputVatAccountCode = ($bucket !== null && $vat > 0) ? $this->router->accountCodeFor($bucket) : null;

                if ($taxCode->isExempt()) {
                    $totals['exempt'] += $net;
                } else {
                    $totals['vatable'] += $net;
                }
                if ($inputVatAccountCode !== null) {
                    $totals['input_vat'] += $vat;
                }
                $totals['total'] += $net + $vat;

                $lines[] = [
                    'model' => [
                        'line_no' => $lineNo,
                        'item_id' => $lineData->item_id,
                        'description' => $lineData->description,
                        'qty' => $lineData->qty,
                        'unit_price' => $lineData->unit_price,
                        'tax_code_id' => $taxCode->id,
                        'vat_bucket' => $bucket,
                        'line_total' => $costCredit,
                        'vat_amount' => $vat,
                        'expense_or_asset_account_id' => $accountId,
                    ],
                    'item' => $item,
                    'cost_credit' => $costCredit,
                    'vat' => $vat,
                    'input_vat_account_code' => $inputVatAccountCode,
                    'account_id' => $accountId,
                    'tax_code_id' => $taxCode->id,
                    'bucket' => $bucket,
                    'desc' => $lineData->description,
                ];
                $lineNo++;
            }

            $memo = new DebitMemo;
            $memo->forceFill([
                'company_id' => $company->id,
                'vendor_id' => $vendor->id,
                'memo_date' => $data->memo_date,
                'status' => 'posted',
                'pricing_mode' => $data->pricing_mode,
                'vatable_purchases' => $totals['vatable'],
                'input_vat' => $totals['input_vat'],
                'exempt_purchases' => $totals['exempt'],
                'total' => $totals['total'],
                'memo' => $data->memo,
                'reference_no' => $data->reference_no,
                'external_reference_no' => $data->external_reference_no,
                'created_by' => $data->created_by ?? $actor?->id,
                'approved_by' => $data->approved_by ?? $actor?->id,
                'approved_at' => now(),
            ]);
            $memo->number = $this->numbers->next($company->id, 'debit_memo', Carbon::parse($data->memo_date)->year);
            $memo->save();

            $jeLines = [
                new JournalLineData(
                    account_id: $this->account($company, '2100')->id,
                    debit: $totals['total'],
                    memo: 'AP debit — '.$vendor->name,
                    partner_type: $vendor->getMorphClass(),
                    partner_id: $vendor->id,
                ),
            ];

            foreach ($lines as $line) {
                $memo->lines()->create($line['model']);

                $stockValue = $this->returnStock($company, $line, $memo, $data);
                if ($stockValue === null) {
                    $jeLines[] = new JournalLineData(
                        account_id: $line['account_id'],
                        credit: $line['cost_credit'],
                        memo: 'Purchase return — '.$line['desc'],
                        tax_code_id: $line['tax_code_id'],
                        vat_bucket: $line['bucket'],
                    );
                } else {
                    // Stock leaves at average cost; the vendor's credit may differ.
                    $jeLines[] = new JournalLineData(
                        account_id: $line['account_id'],
                        credit: $stockValue,
                        memo: 'Purchase return — '.$line['desc'],
                        tax_code_id: $line['tax_code_id'],
                        vat_bucket: $line['bucket'],
                    );
                    $variance = $line['cost_credit'] - $stockValue;
                    if ($variance !== 0) {
                        /** @var Item $item */
                        $item = $line['item'];
                        $jeLines[] = new JournalLineData(
                            account_id: $item->cogs_account_id ?? $this->account($company, '5100')->id,
                            debit: max(0, -$variance),
                            credit: max(0, $variance),
                            memo: 'Purchase return price difference — '.$line['desc'],
                        );
                    }
                }

                if ($line['input_vat_account_code'] !== null) {
                    $jeLines[] = new JournalLineData(
                        account_id: $this->account($company, $line['input_vat_account_code'])->id,
                        credit: $line['vat'],
                        memo: 'Input VAT reversal',
                        vat_bucket: $line['bucket'],
                    );
                }
            }

            $entry = $this->post->handle(new JournalEntryData(
                company_id: $company->id,
                entry_date: $data->memo_date,
                memo: 'Debit memo '.(string) $memo->number,
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

    /** A stocked item may only leave through its own inventory account, as it came in. */
    private function costAccountFor(Company $company, int $lineNo, BillLineData $line, ?Item $item): int
    {
        if ($item === null || $item->type !== ItemType::Inventory) {
            return $line->expense_or_asset_account_id;
        }
        if ($item->inventory_account_id === null) {
            throw InventoryAccountException::missing($lineNo, $item->name);
        }
        if ($line->expense_or_asset_account_id !== $item->inventory_account_id) {
            $accounts = Account::query()->withoutGlobalScopes()->where('company_id', $company->id)
                ->whereIn('id', [$item->inventory_account_id, $line->expense_or_asset_account_id])->get()->keyBy('id');
            $label = fn (int $id): string => ($a = $accounts->get($id)) !== null ? "{$a->code} {$a->name}" : "account #{$id}";

            throw InventoryAccountException::mismatch($lineNo, $item->name, $label($item->inventory_account_id), $label($line->expense_or_asset_account_id));
        }

        return $item->inventory_account_id;
    }

    /**
     * Take a returned stocked item back out of inventory; the value it leaves
     * at is what the inventory account is credited. Null for non-stock lines.
     *
     * @param  array<string, mixed>  $line
     */
    private function returnStock(Company $company, array $line, DebitMemo $memo, DebitMemoData $data): ?int
    {
        $item = $line['item'];
        if (! $item instanceof Item || $item->type !== ItemType::Inventory) {
            return null;
        }

        return $this->inventory->issue($item, Quantity::toUnits($line['model']['qty']), $company,
            new Movement($data->memo_date, StockMovementKind::ReturnOut, $memo, $memo->number, $line['desc'], $memo->created_by));
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
