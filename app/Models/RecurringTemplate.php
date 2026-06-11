<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurringKind;
use App\Enums\RecurringSchedule;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\RecurringTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property RecurringKind $kind
 * @property array<string, mixed>|null $payload
 * @property RecurringSchedule $schedule
 * @property int $day_of_month
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon $next_run_on
 * @property bool $auto_post
 * @property bool $is_active
 */
final class RecurringTemplate extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RecurringTemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => RecurringKind::class,
            'payload' => 'array',
            'schedule' => RecurringSchedule::class,
            'day_of_month' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_run_on' => 'date',
            'auto_post' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RecurringRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(RecurringRun::class);
    }
}
