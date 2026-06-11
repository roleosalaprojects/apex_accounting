<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxpayerType;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $tin
 * @property string $branch_code
 * @property TaxpayerType $taxpayer_type
 * @property int $fiscal_year_start_month
 * @property bool $require_approval
 * @property bool $block_negative_inventory
 * @property string $currency_code
 * @property bool $is_active
 */
final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'taxpayer_type' => TaxpayerType::class,
            'fiscal_year_start_month' => 'integer',
            'require_approval' => 'boolean',
            'block_negative_inventory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<AccountingPeriod, $this>
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }
}
