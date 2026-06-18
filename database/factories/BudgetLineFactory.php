<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
final class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'account_id' => Account::factory(),
            'amount' => $this->faker->numberBetween(1_000_00, 1_000_000_00),
        ];
    }
}
