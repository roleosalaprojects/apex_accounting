<?php

declare(strict_types=1);

namespace App\Actions\Receivables;

use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\InvoiceStatus;
use App\Enums\ItemType;
use App\Enums\JournalStatus;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Voids a posted invoice by reversing its entries (reason required) — the
 * sale and, for stocked goods, the cost of sales, which go back into stock at
 * the cost recorded on each line — and marking it voided. Blocked when
 * payments or credit memos are applied — those must be unapplied/voided
 * first (§6.2).
 */
final class VoidInvoice
{
    public function __construct(
        private readonly ReverseJournalEntry $reverse,
        private readonly InventoryService $inventory,
    ) {}

    public function handle(Invoice $invoice, string $reason, ?User $actor = null): Invoice
    {
        if (! $invoice->status->isPosted()) {
            throw new RuntimeException('Only a posted invoice can be voided.');
        }

        return DB::transaction(function () use ($invoice, $reason, $actor): Invoice {
            $invoice->loadCount(['paymentApplications', 'creditMemoApplications']);

            if ($invoice->payment_applications_count > 0 || $invoice->credit_memo_applications_count > 0) {
                throw new RuntimeException('Unapply payments/credit memos before voiding this invoice.');
            }

            foreach ($invoice->lines()->orderBy('line_no')->get() as $line) {
                $item = $line->item_id === null ? null : Item::query()->withoutGlobalScopes()
                    ->where('company_id', $invoice->company_id)->find($line->item_id);

                if ($item instanceof Item && $item->type === ItemType::Inventory) {
                    $this->inventory->receive($item, Quantity::toUnits($line->qty), $line->cogs ?? 0);
                }
            }

            JournalEntry::query()->withoutGlobalScopes()
                ->where('company_id', $invoice->company_id)
                ->where('source_type', $invoice->getMorphClass())
                ->where('source_id', $invoice->id)
                ->where('status', JournalStatus::Posted->value)
                ->whereNull('reversal_of_id')
                ->get()
                ->each(fn (JournalEntry $entry) => $this->reverse->handle($entry, $reason, actor: $actor));

            $invoice->forceFill(['status' => InvoiceStatus::Voided])->save();

            return $invoice;
        });
    }
}
