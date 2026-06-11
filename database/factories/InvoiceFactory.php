<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'invoice_date' => '2026-06-10',
            'due_date' => '2026-07-10',
            'status' => InvoiceStatus::Draft,
            'pricing_mode' => PricingMode::VatInclusive,
        ];
    }
}
