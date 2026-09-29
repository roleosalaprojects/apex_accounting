<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-item running quantity (ten-thousandths), weighted-average unit cost
 * (centavos × 10000) and the exact value of the stock (centavos), which always
 * equals what was posted to the item's inventory account. (§9)
 *
 * @property int $id
 * @property int $company_id
 * @property int $item_id
 * @property int $qty_units
 * @property int $avg_cost_x10000
 * @property int $value
 */
final class ItemValuation extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'qty_units' => 'integer',
            'avg_cost_x10000' => 'integer',
            'value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
