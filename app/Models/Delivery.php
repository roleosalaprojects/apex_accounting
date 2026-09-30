<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A delivery receipt: the part of a sales order that went out on one trip.
 * Commercial paper only — stock and revenue move when the invoice posts.
 *
 * @property int $id
 * @property int $company_id
 * @property int $sales_order_id
 * @property string|null $number
 * @property Carbon $delivery_date
 * @property string|null $received_by
 * @property string|null $notes
 * @property int|null $created_by
 */
final class Delivery extends Model
{
    use BelongsToCompany;
    use HasCreator;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['delivery_date' => 'date'];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return HasMany<DeliveryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class);
    }
}
