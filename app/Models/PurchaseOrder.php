<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A purchase order (§7): a commercial document that converts into a posted Bill.
 * It carries no ledger impact of its own.
 *
 * @property int $id
 * @property int $company_id
 * @property int $vendor_id
 * @property Carbon $order_date
 * @property Carbon|null $expected_date
 * @property string $pricing_mode
 * @property string|null $reference
 * @property string $status
 * @property int|null $bill_id
 */
final class PurchaseOrder extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
        ];
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /** Net-of-tax subtotal in minor units, for listing only. */
    public function subtotal(): int
    {
        return (int) $this->lines->sum(fn (PurchaseOrderLine $l): float => (float) $l->qty * $l->unit_price);
    }
}
