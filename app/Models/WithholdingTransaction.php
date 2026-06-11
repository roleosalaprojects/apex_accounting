<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 2307 / 0619-E / 1601-EQ data source (§7).
 *
 * @property int $id
 * @property int $company_id
 * @property int $vendor_id
 * @property int|null $vendor_payment_id
 * @property int $withholding_code_id
 * @property string $atc
 * @property Money $base
 * @property int $rate_bp
 * @property Money $ewt
 */
final class WithholdingTransaction extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'base' => MoneyCast::class,
            'rate_bp' => 'integer',
            'ewt' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<WithholdingCode, $this>
     */
    public function withholdingCode(): BelongsTo
    {
        return $this->belongsTo(WithholdingCode::class);
    }
}
