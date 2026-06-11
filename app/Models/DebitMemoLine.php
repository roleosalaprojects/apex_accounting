<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\VatBucket;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $debit_memo_id
 * @property int $line_no
 * @property string $description
 * @property Money $unit_price
 * @property int|null $tax_code_id
 * @property VatBucket|null $vat_bucket
 * @property Money $line_total
 * @property Money $vat_amount
 * @property int $expense_or_asset_account_id
 */
final class DebitMemoLine extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'unit_price' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'vat_amount' => MoneyCast::class,
            'vat_bucket' => VatBucket::class,
        ];
    }

    /**
     * @return BelongsTo<DebitMemo, $this>
     */
    public function debitMemo(): BelongsTo
    {
        return $this->belongsTo(DebitMemo::class);
    }
}
