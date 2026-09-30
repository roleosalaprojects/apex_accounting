<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementKind;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One line of the stock ledger (§9): a signed quantity and value that moved
 * for an item, and the document that moved it. The ledger's sum per item is
 * the item's valuation.
 *
 * @property int $id
 * @property int $company_id
 * @property int $item_id
 * @property Carbon $moved_on
 * @property StockMovementKind $kind
 * @property int $qty_units signed ten-thousandths
 * @property int $value signed centavos
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $reference
 * @property string|null $description
 * @property int|null $created_by
 */
final class StockMovement extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'moved_on' => 'date',
            'kind' => StockMovementKind::class,
            'qty_units' => 'integer',
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

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
