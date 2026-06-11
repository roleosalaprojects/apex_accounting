<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Actions\Receivables\ReceiveCustomerPayment;
use App\Data\Receivables\CustomerPaymentData;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CreateCustomerPayment extends CreateRecord
{
    protected static string $resource = CustomerPaymentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Company $company */
        $company = Filament::getTenant();
        /** @var User $actor */
        $actor = Auth::user();

        $apps = array_map(fn (array $a): array => [
            'invoice_id' => (int) $a['invoice_id'],
            'amount' => (int) round(((float) $a['amount']) * 100),
        ], $data['applications']);

        try {
            return app(ReceiveCustomerPayment::class)->handle(CustomerPaymentData::from([
                'company_id' => $company->id,
                'customer_id' => (int) $data['customer_id'],
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'deposit_to_account_id' => (int) $data['deposit_to_account_id'],
                'amount' => (int) round(((float) $data['amount']) * 100),
                'ewt_withheld' => (int) round(((float) ($data['ewt_withheld'] ?? 0)) * 100),
                'applications' => $apps,
            ]), $actor);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not record payment')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
