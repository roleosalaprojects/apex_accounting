<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Fund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fund>
 */
final class FundFactory extends Factory
{
    protected $model = Fund::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('Fund-###')),
            'name' => $this->faker->words(2, true),
            'is_active' => true,
        ];
    }
}
