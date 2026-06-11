<?php

declare(strict_types=1);

namespace App\Filament\Resources\VendorPayments\Pages;

use App\Actions\Payables\PayBill;
use App\Data\Payables\PayBillData;
use App\Filament\Resources\VendorPayments\VendorPaymentResource;
use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

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
            'bill_id' => (int) $a['bill_id'],
            'amount' => (int) round(((float) $a['amount']) * 100),
        ], $data['applications']);

        try {
            return app(PayBill::class)->handle(PayBillData::from([
                'company_id' => $company->id,
                'vendor_id' => (int) $data['vendor_id'],
                'payment_date' => $data['payment_date'],
                'method' => $data['method'],
                'paid_from_account_id' => (int) $data['paid_from_account_id'],
                'withholding_code_id' => isset($data['withholding_code_id']) ? (int) $data['withholding_code_id'] : null,
                'external_reference_no' => $data['external_reference_no'] ?? null,
                'applications' => $apps,
            ]), $actor);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Could not record payment')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
