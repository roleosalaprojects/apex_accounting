<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AssetCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int $fixed_asset_account_id
 * @property int $accum_depreciation_account_id
 * @property int $depreciation_expense_account_id
 * @property int $default_useful_life_months
 * @property string $method
 */
final class AssetCategory extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<AssetCategoryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'default_useful_life_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function fixedAssetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'fixed_asset_account_id');
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
