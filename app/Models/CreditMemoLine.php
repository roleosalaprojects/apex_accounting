<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $credit_memo_id
 * @property int $line_no
 * @property string $description
 * @property string $qty
 * @property Money $unit_price
 * @property int|null $tax_code_id
 * @property Money $line_total
 * @property Money $vat_amount
 * @property int $income_account_id
 */
final class CreditMemoLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'vat_amount' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<CreditMemo, $this>
     */
    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(CreditMemo::class);
    }
}
