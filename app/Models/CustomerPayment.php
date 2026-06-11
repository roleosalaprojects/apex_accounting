<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Money;
use Database\Factories\CustomerPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $customer_id
 * @property string|null $number
 * @property Carbon $payment_date
 * @property PaymentMethod $method
 * @property int $deposit_to_account_id
 * @property Money $amount
 * @property Money $ewt_withheld
 * @property string $status
 * @property string|null $collection_receipt_no
 * @property int|null $journal_entry_id
 */
final class CustomerPayment extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    /** @use HasFactory<CustomerPaymentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'method' => PaymentMethod::class,
            'amount' => MoneyCast::class,
            'ewt_withheld' => MoneyCast::class,
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<PaymentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
