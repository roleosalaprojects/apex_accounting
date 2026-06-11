<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
final class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'fiscal_year' => 2026,
            'period_no' => 1,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-01-31',
            'status' => PeriodStatus::Open,
        ];
    }
}
