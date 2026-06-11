<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountSubtype;
use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
final class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $subtype = AccountSubtype::Expense;

        return [
            'company_id' => Company::factory(),
            'code' => (string) $this->faker->unique()->numberBetween(6000, 6999),
            'name' => $this->faker->words(2, true),
            'type' => $subtype->type(),
            'subtype' => $subtype,
            'normal_balance' => $subtype->normalBalance(),
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function subtype(AccountSubtype $subtype, string $code): self
    {
        return $this->state([
            'subtype' => $subtype,
            'type' => $subtype->type(),
            'normal_balance' => $subtype->normalBalance(),
            'code' => $code,
        ]);
    }
}
