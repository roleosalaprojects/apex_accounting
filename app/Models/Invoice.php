<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Money;
use Database\Factories\InvoiceFactory;
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
 * @property Carbon $invoice_date
 * @property Carbon|null $due_date
 * @property InvoiceStatus $status
 * @property PricingMode $pricing_mode
 * @property bool $is_opening
 * @property Money $vatable_sales
 * @property Money $vat_amount
 * @property Money $exempt_sales
 * @property Money $zero_rated_sales
 * @property Money $total
 * @property int|null $journal_entry_id
 */
final class Invoice extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'pricing_mode' => PricingMode::class,
            'is_opening' => 'boolean',
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
            'vatable_sales' => MoneyCast::class,
            'vat_amount' => MoneyCast::class,
            'exempt_sales' => MoneyCast::class,
            'zero_rated_sales' => MoneyCast::class,
            'total' => MoneyCast::class,
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
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /**
     * @return HasMany<PaymentApplication, $this>
     */
    public function paymentApplications(): HasMany
    {
        return $this->hasMany(PaymentApplication::class);
    }

    /**
     * @return HasMany<CreditMemoApplication, $this>
     */
    public function creditMemoApplications(): HasMany
    {
        return $this->hasMany(CreditMemoApplication::class);
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

    /**
     * Amount settled so far = payments applied + credit memos applied.
     */
    public function appliedAmount(): int
    {
        return (int) $this->paymentApplications()->sum('amount')
            + (int) $this->creditMemoApplications()->sum('amount');
    }

    public function outstanding(): int
    {
        return $this->total->minor - $this->appliedAmount();
    }
}
