<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line on a purchase order. Mirrors BillLineData so conversion is a direct map.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $item_id
 * @property string $description
 * @property string $qty
 * @property int $unit_price
 * @property int $tax_code_id
 * @property int $expense_account_id
 * @property string|null $vat_bucket
 */
final class PurchaseOrderLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
