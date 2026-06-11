<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PricingMode;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Money;
use Database\Factories\CreditMemoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property string|null $number
 * @property Carbon $memo_date
 * @property string $status
 * @property PricingMode $pricing_mode
 * @property Money $vatable_sales
 * @property Money $vat_amount
 * @property Money $exempt_sales
 * @property Money $zero_rated_sales
 * @property Money $total
 * @property int|null $journal_entry_id
 */
final class CreditMemo extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    /** @use HasFactory<CreditMemoFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'memo_date' => 'date',
            'pricing_mode' => PricingMode::class,
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
            'vatable_sales' => MoneyCast::class,
            'vat_amount' => MoneyCast::class,
            'exempt_sales' => MoneyCast::class,
            'zero_rated_sales' => MoneyCast::class,
            'total' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<CreditMemoLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(CreditMemoLine::class);
    }

    /**
     * @return HasMany<CreditMemoApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(CreditMemoApplication::class);
    }
}
