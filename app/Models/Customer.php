<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string|null $tin
 * @property bool $is_withholding_agent
 * @property int $terms_days
 * @property Money|null $credit_limit
 */
final class Customer extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_withholding_agent' => 'boolean',
            'terms_days' => 'integer',
            'credit_limit' => MoneyCast::class,
        ];
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
