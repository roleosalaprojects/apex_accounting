<?php

declare(strict_types=1);

namespace App\Actions\Payables;

use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\JournalStatus;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voids a posted bill: reverses its entry (reason required), takes any stock
 * it received back out at the cost it came in at, and marks it voided.
 * Blocked while payments or debit memos are applied — void those first.
 */
final class VoidBill
{
    public function __construct(
        private readonly ReverseJournalEntry $reverse,
        private readonly InventoryService $inventory,
    ) {}

    public function handle(Bill $bill, string $reason, ?User $actor = null): Bill
    {
        if (! in_array($bill->status, [InvoiceStatus::Posted, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid], true)) {
            throw new RuntimeException('Only a posted bill can be voided.');
        }

        return DB::transaction(function () use ($bill, $reason, $actor): Bill {
            $bill->loadCount(['applications', 'debitMemoApplications']);

            if ($bill->applications_count > 0 || $bill->debit_memo_applications_count > 0) {
                throw new RuntimeException("Bill {$bill->number} has payments applied. Void its payments first.");
            }

            /** @var Company $company */
            $company = Company::query()->withoutGlobalScopes()->findOrFail($bill->company_id);

            if (! $bill->is_opening) {
                foreach ($bill->lines()->orderBy('line_no')->get() as $line) {
                    $item = $line->item_id === null ? null : Item::query()->withoutGlobalScopes()
                        ->where('company_id', $company->id)->find($line->item_id);

                    if ($item instanceof Item && $item->type === ItemType::Inventory) {
                        $this->inventory->takeBack($item, Quantity::toUnits($line->qty), $line->line_total->minor, $company);
                    }
                }
            }

            JournalEntry::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('source_type', $bill->getMorphClass())
                ->where('source_id', $bill->id)
                ->where('status', JournalStatus::Posted->value)
                ->whereNull('reversal_of_id')
                ->get()
                ->each(fn (JournalEntry $entry) => $this->reverse->handle($entry, $reason, actor: $actor));

            $bill->forceFill(['status' => InvoiceStatus::Voided])->save();

            return $bill;
        });
    }
}
