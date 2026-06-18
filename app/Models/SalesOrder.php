<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A sales order / quotation (§6): a commercial document that converts into a
 * posted Invoice. It carries no ledger impact of its own.
 *
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property Carbon $order_date
 * @property Carbon|null $expiry_date
 * @property string $pricing_mode
 * @property string|null $reference
 * @property string $status
 * @property int|null $invoice_id
 */
final class SalesOrder extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    /**
     * @return HasMany<SalesOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** Net-of-tax subtotal in minor units, for listing only. */
    public function subtotal(): int
    {
        return (int) $this->lines->sum(fn (SalesOrderLine $l): float => (float) $l->qty * $l->unit_price);
    }
}
