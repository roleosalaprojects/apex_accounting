<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Data\Ledger\JournalLineData;
use App\Data\Payables\BillData;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\StockMovementKind;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\Movement;
use App\Services\Numbering\NumberGenerator;
use App\Services\Payables\BillLineCalculator;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\LaravelData\DataCollection;

/**
 * Posts a vendor Bill with input VAT three-bucket attribution (§5.3, §7):
 *   Dr expense/asset (per line; exempt-bucket lines include their VAT in cost)
 *   Dr 1400 Input VAT            (direct_vatable VAT)
 *   Dr 1410 Deferred Common VAT  (common VAT)
 *      Cr 2100 AP (partner=vendor)
 *
 * Lines for stocked items are received into weighted-average stock (§9), so
 * their cost may only be debited to the item's inventory account; any other
 * account is refused, keeping the stock subledger equal to the ledger.
 */
final class PostBill
{
    public function __construct(
        private readonly PostJournalEntry $post,
        private readonly BillLineCalculator $calculator,
        private readonly NumberGenerator $numbers,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Post a new bill — or, given a draft awaiting approval, post that draft
     * in place so its id, attachments and history carry over.
     */
    public function handle(BillData $data, ?User $actor = null, ?Bill $draft = null): Bill
    {
        return DB::transaction(function () use ($data, $actor, $draft): Bill {
            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($data->company_id);
            /** @var Vendor $vendor */
            $vendor = Vendor::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)->findOrFail($data->vendor_id);

            if ($data->lines->count() === 0) {
                throw new RuntimeException('A bill needs at least one line.');
            }

            $computed = $this->calculator->compute($company, $data);

            $dueDate = $data->due_date
                ?? Carbon::parse($data->bill_date)->addDays($vendor->terms_days)->toDateString();

            $bill = $draft ?? new Bill;
            if ($draft !== null) {
                if ($draft->status !== InvoiceStatus::Draft) {
                    throw new RuntimeException('Only a draft bill can be posted.');
                }
                $draft->lines()->delete();
            }
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
                'purchase_order_id' => $data->purchase_order_id,
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

                // An opening bill carries AP forward against 3950; its stock is
                // already in the opening count, so receiving it would double it.
                if (! $data->is_opening) {
                    $this->receiveInventory($line, $bill, $data);
                }
            }

            $entry = $this->post->handle($this->buildJournalData($company, $vendor, $bill, $data, $computed), $actor);
            $bill->forceFill(['journal_entry_id' => $entry->id])->save();

            return $bill->load('lines');
        });
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
                        department_id: $line['dims']['department_id'],
                        project_id: $line['dims']['project_id'],
                        fund_id: $line['dims']['fund_id'],
                        branch_id: $line['dims']['branch_id'],
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
    private function receiveInventory(array $line, Bill $bill, BillData $data): void
    {
        $item = $line['item'];
        if (! $item instanceof Item || $item->type !== ItemType::Inventory) {
            return;
        }

        $this->inventory->receive(
            $item,
            Quantity::toUnits($line['model']['qty']),
            $line['cost_debit'],
            new Movement($data->bill_date, StockMovementKind::Receipt, $bill, $bill->number, $line['desc'], $bill->created_by),
        );
    }

    private function account(Company $company, string $code): Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)->where('code', $code)->firstOrFail();
    }
}
