<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\AssetStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $asset_category_id
 * @property string|null $number
 * @property string $name
 * @property Carbon $acquisition_date
 * @property Money $acquisition_cost
 * @property Money $salvage_value
 * @property int $useful_life_months
 * @property AssetStatus $status
 * @property Carbon|null $in_service_date
 */
final class Asset extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'in_service_date' => 'date',
            'disposed_at' => 'date',
            'acquisition_cost' => MoneyCast::class,
            'salvage_value' => MoneyCast::class,
            'useful_life_months' => 'integer',
            'status' => AssetStatus::class,
        ];
    }

    /**
     * @return BelongsTo<AssetCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * @return HasMany<DepreciationEntry, $this>
     */
    public function depreciationEntries(): HasMany
    {
        return $this->hasMany(DepreciationEntry::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function depreciableBase(): int
    {
        return $this->acquisition_cost->minor - $this->salvage_value->minor;
    }

    public function accumulatedDepreciation(): int
    {
        return (int) $this->depreciationEntries()->sum('amount');
    }
}
