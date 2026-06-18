<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceStatus;
use App\Enums\PricingMode;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Currencies;
use App\Support\Money;
use Database\Factories\BillFactory;
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
 * @property Carbon $bill_date
 * @property Carbon|null $due_date
 * @property InvoiceStatus $status
 * @property PricingMode $pricing_mode
 * @property bool $is_opening
 * @property Money $vatable_purchases
 * @property Money $input_vat
 * @property Money $exempt_purchases
 * @property Money $total
 * @property string $currency_code
 * @property float $exchange_rate
 * @property int|null $foreign_total
 * @property int|null $journal_entry_id
 */
final class Bill extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    /** @use HasFactory<BillFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
            'pricing_mode' => PricingMode::class,
            'is_opening' => 'boolean',
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
            'vatable_purchases' => MoneyCast::class,
            'input_vat' => MoneyCast::class,
            'exempt_purchases' => MoneyCast::class,
            'total' => MoneyCast::class,
            'exchange_rate' => 'float',
            'foreign_total' => 'integer',
        ];
    }

    /** True when entered in a currency other than the functional (PHP). */
    public function isForeignCurrency(): bool
    {
        return $this->currency_code !== Currencies::FUNCTIONAL;
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<BillLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class);
    }

    /**
     * @return HasMany<BillApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(BillApplication::class);
    }

    /**
     * @return HasMany<DebitMemoApplication, $this>
     */
    public function debitMemoApplications(): HasMany
    {
        return $this->hasMany(DebitMemoApplication::class);
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

    public function appliedAmount(): int
    {
        return (int) $this->applications()->sum('amount')
            + (int) $this->debitMemoApplications()->sum('amount');
    }

    public function outstanding(): int
    {
        return $this->total->minor - $this->appliedAmount();
    }
}
