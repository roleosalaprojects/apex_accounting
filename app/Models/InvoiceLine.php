<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $line_no
 * @property int|null $item_id
 * @property string $description
 * @property string $qty
 * @property Money $unit_price
 * @property int|null $tax_code_id
 * @property Money $line_total
 * @property Money $vat_amount
 * @property int $income_account_id
 */
final class InvoiceLine extends Model
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<TaxCode, $this>
     */
    public function taxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class);
    }
}
