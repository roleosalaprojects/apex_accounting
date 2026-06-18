<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Pages;

use App\Actions\Receivables\PostInvoice;
use App\Data\Receivables\InvoiceData;
use App\Exceptions\Ledger\LedgerException;
use App\Filament\Resources\Invoices\InvoiceResource;
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
 * Issues an invoice through PostInvoice (§2 — all writes go through Actions).
 */
class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

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
            'income_account_id' => (int) $line['income_account_id'],
        ], $data['lines']);

        try {
            $invoice = app(PostInvoice::class)->handle(InvoiceData::from([
                'company_id' => $company->id,
                'customer_id' => (int) $data['customer_id'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'pricing_mode' => $data['pricing_mode'],
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
            ]), $actor);

            if ($currency !== Currencies::FUNCTIONAL) {
                $invoice->update([
                    'currency_code' => $currency,
                    'exchange_rate' => $rate,
                    'foreign_total' => $rate > 0 ? (int) round($invoice->total->minor / $rate) : null,
                ]);
            }

            return $invoice;
        } catch (LedgerException $e) {
            Notification::make()->danger()->title('Could not post invoice')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
