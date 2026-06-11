<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ItemType;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $sku
 * @property string $name
 * @property ItemType $type
 * @property bool $is_vat_exempt_item
 * @property int|null $income_account_id
 * @property int|null $cogs_account_id
 * @property int|null $inventory_account_id
 * @property Money $default_sales_price
 * @property Money $default_purchase_price
 * @property string $unit
 */
final class Item extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'is_vat_exempt_item' => 'boolean',
            'default_sales_price' => MoneyCast::class,
            'default_purchase_price' => MoneyCast::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasOne<ItemValuation, $this>
     */
    public function valuation(): HasOne
    {
        return $this->hasOne(ItemValuation::class);
    }
}
