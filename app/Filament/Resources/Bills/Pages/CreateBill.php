<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bills\Pages;

use App\Actions\Payables\PostBill;
use App\Data\Payables\BillData;
use App\Exceptions\Ledger\LedgerException;
use App\Filament\Resources\Bills\BillResource;
use App\Models\Company;
use App\Models\User;
use App\Support\Currencies;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enters a vendor bill through PostBill (§5.3, §7 — all writes go through Actions).
 */
class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Company $company */
        $company = Filament::getTenant();
        /** @var User $actor */
        $actor = Auth::user();

        $currency = $data['currency_code'] ?? Currencies::FUNCTIONAL;
        $rate = $currency === Currencies::FUNCTIONAL ? 1.0 : (float) ($data['exchange_rate'] ?? 1);

        // Foreign line prices convert to functional (PHP) at the document rate so
        // the ledger stays in functional currency; rate is 1.0 for PHP (no-op).
        $lines = array_map(fn (array $line): array => [
            'item_id' => $line['item_id'] ?? null,
            'description' => $line['description'],
            'qty' => (string) $line['qty'],
            'unit_price' => (int) round(((float) $line['unit_price']) * 100 * $rate),
            'tax_code_id' => (int) $line['tax_code_id'],
            'vat_bucket' => $line['vat_bucket'] ?? null,
            'expense_or_asset_account_id' => (int) $line['expense_or_asset_account_id'],
        ], $data['lines']);

        try {
            $bill = app(PostBill::class)->handle(BillData::from([
                'company_id' => $company->id,
                'vendor_id' => (int) $data['vendor_id'],
                'bill_date' => $data['bill_date'],
                'due_date' => $data['due_date'] ?? null,
                'pricing_mode' => $data['pricing_mode'],
                'external_reference_no' => $data['external_reference_no'] ?? null,
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
            ]), $actor);

            if ($currency !== Currencies::FUNCTIONAL) {
                $bill->update([
                    'currency_code' => $currency,
                    'exchange_rate' => $rate,
                    'foreign_total' => $rate > 0 ? (int) round($bill->total->minor / $rate) : null,
                ]);
            }

            return $bill;
        } catch (LedgerException $e) {
            Notification::make()->danger()->title('Could not post bill')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
