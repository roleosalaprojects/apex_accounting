<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PeriodStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AccountingPeriodFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property int $period_no
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property PeriodStatus $status
 */
final class AccountingPeriod extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<AccountingPeriodFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'period_no' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => PeriodStatus::class,
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === PeriodStatus::Open;
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'period_id');
    }

    /**
     * @param  Builder<AccountingPeriod>  $query
     */
    public function scopeContaining(Builder $query, string $date): void
    {
        // whereDate normalises away any time component the date cast may persist.
        $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }
}
