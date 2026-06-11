<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('CUST-####')),
            'name' => $this->faker->company(),
            'tin' => $this->faker->numerify('###-###-###-#####'),
            'is_withholding_agent' => false,
            'terms_days' => 30,
            'credit_limit' => null,
        ];
    }

    public function withholdingAgent(): self
    {
        return $this->state(['is_withholding_agent' => true]);
    }
}
