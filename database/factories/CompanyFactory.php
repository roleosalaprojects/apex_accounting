<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaxpayerType;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'tin' => $this->faker->numerify('###-###-###-#####'),
            'branch_code' => '00000',
            'address' => $this->faker->address(),
            'taxpayer_type' => TaxpayerType::Vat,
            'fiscal_year_start_month' => 1,
            'require_approval' => false,
            'block_negative_inventory' => true,
            'currency_code' => 'PHP',
            'is_active' => true,
        ];
    }

    public function nonVat(): self
    {
        return $this->state(['taxpayer_type' => TaxpayerType::NonVat]);
    }

    public function requiresApproval(): self
    {
        return $this->state(['require_approval' => true]);
    }
}
