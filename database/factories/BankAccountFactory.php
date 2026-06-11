<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
final class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_id' => Account::factory(),
            'bank_name' => $this->faker->company().' Bank',
            'account_no' => $this->faker->numerify('####-####-####'),
            'is_active' => true,
        ];
    }
}
