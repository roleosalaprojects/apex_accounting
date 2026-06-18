<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankStatementLine>
 */
final class BankStatementLineFactory extends Factory
{
    protected $model = BankStatementLine::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'bank_account_id' => BankAccount::factory(),
            'txn_date' => '2026-03-15',
            'description' => $this->faker->sentence(3),
            'amount' => $this->faker->numberBetween(-500_000_00, 500_000_00),
            'status' => 'unmatched',
            'source' => 'csv',
        ];
    }
}
