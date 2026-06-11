<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $debit_memo_id
 * @property int $bill_id
 * @property Money $amount
 */
final class DebitMemoApplication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => MoneyCast::class];
    }

    /**
     * @return BelongsTo<DebitMemo, $this>
     */
    public function debitMemo(): BelongsTo
    {
        return $this->belongsTo(DebitMemo::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
