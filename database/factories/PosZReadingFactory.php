<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PosZReadingStatus;
use App\Models\Company;
use App\Models\PosZReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosZReading>
 */
final class PosZReadingFactory extends Factory
{
    protected $model = PosZReading::class;

    public function definition(): array
    {
        // A balanced day: tenders 162k = sales 150k + VAT 12k, less 2k discount.
        return [
            'company_id' => Company::factory(),
            'business_date' => '2026-06-15',
            'reference' => 'Z-'.$this->faker->unique()->numberBetween(1, 9999),
            'vatable_sales' => 100_000_00,
            'exempt_sales' => 50_000_00,
            'zero_rated_sales' => 0,
            'vat_amount' => 12_000_00,
            'discounts' => 2_000_00,
            'tenders' => ['cash' => 100_000_00, 'card' => 60_000_00],
            'status' => PosZReadingStatus::Pending,
        ];
    }

    public function imported(): static
    {
        return $this->state(['status' => PosZReadingStatus::Imported]);
    }

    public function dismissed(): static
    {
        return $this->state(['status' => PosZReadingStatus::Dismissed]);
    }
}
