<?php

declare(strict_types=1);

namespace App\Filament\Resources\DebitMemos\Pages;

use App\Actions\Payables\PostDebitMemo;
use App\Data\Payables\DebitMemoData;
use App\Enums\ItemType;
use App\Exceptions\Ledger\LedgerException;
use App\Filament\Resources\DebitMemos\DebitMemoResource;
use App\Models\Bill;
use App\Models\BillLine;
use App\Models\Company;
use App\Models\Item;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/** Posts a vendor debit memo through PostDebitMemo (§2 — all writes go through Actions). */
class CreateDebitMemo extends CreateRecord
{
    protected static string $resource = DebitMemoResource::class;

    /** Opened from a bill (?bill=…): start from that bill's vendor and lines, ready to trim. */
    protected function fillForm(): void
    {
        parent::fillForm();

        $billId = request()->integer('bill');
        if ($billId === 0) {
            return;
        }
        $bill = Bill::query()->with('lines')->find($billId);
        if ($bill === null) {
            return;
        }
        $items = Item::query()->whereIn('id', $bill->lines->pluck('item_id')->filter())->get()->keyBy('id');
        // A stocked item leaves through its inventory account, whatever the bill line said.
        $account = function (BillLine $line) use ($items): int {
            $item = $line->item_id === null ? null : $items->get($line->item_id);

            return $item?->type === ItemType::Inventory && $item->inventory_account_id !== null
                ? $item->inventory_account_id
                : $line->expense_or_asset_account_id;
        };

        $this->form->fill([
            'vendor_id' => $bill->vendor_id,
            'memo_date' => now()->toDateString(),
            'pricing_mode' => $bill->pricing_mode->value,
            'memo' => "Return against bill {$bill->number}",
            'lines' => $bill->lines->map(fn (BillLine $line): array => [
                'item_id' => $line->item_id,
                'description' => $line->description,
                'qty' => (string) $line->qty,
                'unit_price' => number_format($line->unit_price->minor / 100, 2, '.', ''),
                'tax_code_id' => $line->tax_code_id,
                'vat_bucket' => $line->vat_bucket?->value,
                'expense_or_asset_account_id' => $account($line),
            ])->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Company $company */
        $company = Filament::getTenant();
        /** @var User $actor */
        $actor = Auth::user();

        $lines = array_map(fn (array $line): array => [
            'item_id' => $line['item_id'] ?? null,
            'description' => $line['description'],
            'qty' => (string) $line['qty'],
            'unit_price' => (int) round(((float) $line['unit_price']) * 100),
            'tax_code_id' => (int) $line['tax_code_id'],
            'vat_bucket' => $line['vat_bucket'] ?? null,
            'expense_or_asset_account_id' => (int) $line['expense_or_asset_account_id'],
        ], $data['lines']);

        try {
            return app(PostDebitMemo::class)->handle(DebitMemoData::from([
                'company_id' => $company->id,
                'vendor_id' => (int) $data['vendor_id'],
                'memo_date' => $data['memo_date'],
                'pricing_mode' => $data['pricing_mode'],
                'external_reference_no' => $data['external_reference_no'] ?? null,
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
            ]), $actor);
        } catch (LedgerException|RuntimeException $e) {
            Notification::make()->danger()->title('Could not post debit memo')->body($e->getMessage())->send();
            throw new Halt;
        }
    }

    protected function getRedirectUrl(): string
    {
        return DebitMemoResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
