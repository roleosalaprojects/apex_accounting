<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PricingMode;
use App\Models\Company;
use App\Models\CreditMemo;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditMemo>
 */
final class CreditMemoFactory extends Factory
{
    protected $model = CreditMemo::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'memo_date' => '2026-06-20',
            'status' => 'draft',
            'pricing_mode' => PricingMode::VatInclusive,
        ];
    }
}
