<?php

declare(strict_types=1);

namespace App\Filament\Resources\CreditMemos\Pages;

use App\Actions\Receivables\PostCreditMemo;
use App\Data\Receivables\CreditMemoData;
use App\Exceptions\Ledger\LedgerException;
use App\Filament\Resources\CreditMemos\CreditMemoResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Issues a credit memo through PostCreditMemo (§2 — all writes go through Actions).
 */
class CreateCreditMemo extends CreateRecord
{
    protected static string $resource = CreditMemoResource::class;

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
            'income_account_id' => (int) $line['income_account_id'],
        ], $data['lines']);

        try {
            return app(PostCreditMemo::class)->handle(CreditMemoData::from([
                'company_id' => $company->id,
                'customer_id' => (int) $data['customer_id'],
                'memo_date' => $data['memo_date'],
                'pricing_mode' => $data['pricing_mode'],
                'memo' => $data['memo'] ?? null,
                'lines' => $lines,
            ]), $actor);
        } catch (LedgerException|RuntimeException $e) {
            Notification::make()->danger()->title('Could not post credit memo')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
