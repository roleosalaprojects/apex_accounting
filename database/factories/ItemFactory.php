<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
final class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'sku' => strtoupper($this->faker->unique()->bothify('ITEM-####')),
            'name' => $this->faker->words(2, true),
            'type' => ItemType::Inventory,
            'is_vat_exempt_item' => false,
            'default_sales_price' => 0,
            'default_purchase_price' => 0,
            'unit' => 'pc',
            'is_active' => true,
        ];
    }
}
