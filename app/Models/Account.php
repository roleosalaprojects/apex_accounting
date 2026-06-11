<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $parent_id
 * @property string $code
 * @property string $name
 * @property AccountType $type
 * @property AccountSubtype $subtype
 * @property NormalBalance $normal_balance
 * @property bool $is_system
 * @property bool $is_active
 */
final class Account extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'normal_balance' => NormalBalance::class,
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isControl(): bool
    {
        return in_array($this->subtype, [
            AccountSubtype::AccountsReceivable,
            AccountSubtype::AccountsPayable,
        ], true);
    }
}
