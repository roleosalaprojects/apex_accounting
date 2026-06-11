<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Bill;
use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
final class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'vendor_id' => Vendor::factory(),
            'bill_date' => '2026-06-02',
            'due_date' => '2026-07-02',
            'status' => InvoiceStatus::Draft,
            'pricing_mode' => PricingMode::VatExclusive,
        ];
    }
}
