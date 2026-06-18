<?php

declare(strict_types=1);

namespace App\Models;

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
 */
final class SalesOrderLine extends Model
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
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }
}
