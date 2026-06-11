<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Account;
use App\Models\AssetCategory;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCategory>
 */
final class AssetCategoryFactory extends Factory
{
    protected $model = AssetCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->word(),
            'fixed_asset_account_id' => Account::factory(),
            'accum_depreciation_account_id' => Account::factory(),
            'depreciation_expense_account_id' => Account::factory(),
            'default_useful_life_months' => 60,
            'method' => 'straight_line',
            'is_active' => true,
        ];
    }
}
