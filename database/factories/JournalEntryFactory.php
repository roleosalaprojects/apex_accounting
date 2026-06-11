<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\JournalStatus;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
final class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'period_id' => AccountingPeriod::factory(),
            'entry_date' => '2026-06-15',
            'status' => JournalStatus::Draft,
            'total_debits' => 0,
            'total_credits' => 0,
        ];
    }
}
