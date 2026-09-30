<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a sales order. Mirrors InvoiceLineData so conversion is a direct map.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int|null $item_id
 * @property string $description
 * @property string $qty
 * @property int $unit_price
 * @property int $tax_code_id
 * @property int $income_account_id
 * @property string $delivered_qty
 * @property string $invoiced_qty
 */
final class SalesOrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'delivered_qty' => 'decimal:4',
            'invoiced_qty' => 'decimal:4',
            'unit_price' => 'integer',
        ];
    }

    /** Units (×10,000) ordered but not yet delivered. */
    public function unitsToDeliver(): int
    {
        return Quantity::toUnits($this->qty) - Quantity::toUnits($this->delivered_qty);
    }

    /** Units (×10,000) ordered but not yet invoiced. */
    public function unitsToInvoice(): int
    {
        return Quantity::toUnits($this->qty) - Quantity::toUnits($this->invoiced_qty);
    }

    /** Units (×10,000) delivered but not yet invoiced. */
    public function unitsDeliveredUninvoiced(): int
    {
        return max(0, Quantity::toUnits($this->delivered_qty) - Quantity::toUnits($this->invoiced_qty));
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
