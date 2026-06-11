<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Company;
use App\Models\Vendor;
use App\Models\VendorPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorPayment>
 */
final class VendorPaymentFactory extends Factory
{
    protected $model = VendorPayment::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'vendor_id' => Vendor::factory(),
            'payment_date' => '2026-06-20',
            'method' => PaymentMethod::Check,
            'paid_from_account_id' => 1,
            'gross_applied' => 0,
            'ewt' => 0,
            'net_paid' => 0,
            'status' => 'posted',
        ];
    }
}
