<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PricingMode;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $vendor_id
 * @property string|null $number
 * @property Carbon $memo_date
 * @property string $status
 * @property PricingMode $pricing_mode
 * @property Money $vatable_purchases
 * @property Money $input_vat
 * @property Money $exempt_purchases
 * @property Money $total
 * @property int|null $journal_entry_id
 */
final class DebitMemo extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'memo_date' => 'date',
            'pricing_mode' => PricingMode::class,
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
            'vatable_purchases' => MoneyCast::class,
            'input_vat' => MoneyCast::class,
            'exempt_purchases' => MoneyCast::class,
            'total' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<DebitMemoLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DebitMemoLine::class);
    }

    /**
     * @return HasMany<DebitMemoApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(DebitMemoApplication::class);
    }
}
