<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PosZReadingStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\PosZReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One POS end-of-day Z-reading staged in the integration inbox (§14). Amounts are
 * minor units. Pushed by Apex POS; turned into a draft journal entry only when an
 * admin imports it — see App\Actions\Integration\ImportPosZReading.
 *
 * @property int $id
 * @property int $company_id
 * @property Carbon $business_date
 * @property string|null $reference
 * @property int $vatable_sales
 * @property int $exempt_sales
 * @property int $zero_rated_sales
 * @property int $vat_amount
 * @property int $discounts
 * @property array<string, int>|null $tenders
 * @property PosZReadingStatus $status
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property int|null $imported_by
 * @property Carbon|null $imported_at
 * @property-read JournalEntry|null $journalEntry
 */
final class PosZReading extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<PosZReadingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'vatable_sales' => 'integer',
            'exempt_sales' => 'integer',
            'zero_rated_sales' => 'integer',
            'vat_amount' => 'integer',
            'discounts' => 'integer',
            'tenders' => 'array',
            'status' => PosZReadingStatus::class,
            'imported_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === PosZReadingStatus::Pending;
    }

    /**
     * Total sales across VAT classes (excludes output VAT), in minor units.
     */
    public function totalSales(): int
    {
        return $this->vatable_sales + $this->exempt_sales + $this->zero_rated_sales;
    }

    /**
     * The payload shape consumed by PosSalesMapper when building journal lines.
     *
     * @return array<string, mixed>
     */
    public function toMapperPayload(): array
    {
        return [
            'vatable_sales' => $this->vatable_sales,
            'exempt_sales' => $this->exempt_sales,
            'zero_rated_sales' => $this->zero_rated_sales,
            'vat_amount' => $this->vat_amount,
            'discounts' => $this->discounts,
            'tenders' => $this->tenders ?? [],
        ];
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
