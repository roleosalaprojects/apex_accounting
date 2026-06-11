<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
final class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('VEND-####')),
            'name' => $this->faker->company(),
            'tin' => $this->faker->numerify('###-###-###-#####'),
            'is_vat_registered' => true,
            'terms_days' => 30,
        ];
    }
}
