<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
final class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fiscal_year' => 2026,
            'name' => $this->faker->unique()->words(2, true).' Budget',
            'status' => 'active',
        ];
    }
}
