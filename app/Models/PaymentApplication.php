<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_payment_id
 * @property int $invoice_id
 * @property Money $amount
 */
final class PaymentApplication extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
        ];
    }

    /**
     * @return BelongsTo<CustomerPayment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
