<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecurringKind;
use App\Enums\RecurringSchedule;
use App\Models\Company;
use App\Models\RecurringTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTemplate>
 */
final class RecurringTemplateFactory extends Factory
{
    protected $model = RecurringTemplate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->words(2, true),
            'kind' => RecurringKind::JournalEntry,
            'payload' => null,
            'schedule' => RecurringSchedule::Monthly,
            'day_of_month' => 1,
            'starts_on' => '2026-06-01',
            'ends_on' => null,
            'next_run_on' => '2026-06-01',
            'auto_post' => false,
            'is_active' => true,
        ];
    }
}
