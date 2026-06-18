<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaxReturnType;
use App\Models\Company;
use App\Models\TaxReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxReturn>
 */
final class TaxReturnFactory extends Factory
{
    protected $model = TaxReturn::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'type' => TaxReturnType::Vat2550Q->value,
            'fiscal_year' => 2026,
            'quarter' => 2,
            'period_start' => '2026-04-01',
            'period_end' => '2026-06-30',
            'figures' => ['vat_payable' => 0],
            'status' => 'draft',
        ];
    }
}
