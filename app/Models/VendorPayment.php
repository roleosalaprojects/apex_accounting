<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Money;
use Database\Factories\VendorPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $vendor_id
 * @property string|null $number
 * @property string|null $voucher_no
 * @property Carbon $payment_date
 * @property PaymentMethod $method
 * @property int $paid_from_account_id
 * @property Money $gross_applied
 * @property Money $ewt
 * @property Money $net_paid
 * @property string $status
 * @property int|null $journal_entry_id
 */
final class VendorPayment extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    /** @use HasFactory<VendorPaymentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'method' => PaymentMethod::class,
            'gross_applied' => MoneyCast::class,
            'ewt' => MoneyCast::class,
            'net_paid' => MoneyCast::class,
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
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
     * @return HasMany<BillApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(BillApplication::class);
    }

    /**
     * @return HasMany<WithholdingTransaction, $this>
     */
    public function withholdingTransactions(): HasMany
    {
        return $this->hasMany(WithholdingTransaction::class);
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
