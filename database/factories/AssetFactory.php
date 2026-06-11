<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
final class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'asset_category_id' => AssetCategory::factory(),
            'name' => $this->faker->words(2, true),
            'acquisition_date' => '2026-06-01',
            'acquisition_cost' => 120_000_00,
            'salvage_value' => 0,
            'useful_life_months' => 36,
            'status' => AssetStatus::Draft,
        ];
    }
}
