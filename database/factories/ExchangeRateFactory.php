<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
final class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'currency_code' => 'USD',
            'rate_date' => '2026-06-01',
            'rate' => 56.0,
        ];
    }
}
