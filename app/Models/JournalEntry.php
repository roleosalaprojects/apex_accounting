<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\JournalStatus;
use App\Exceptions\Ledger\ImmutableEntryException;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasDocumentMeta;
use App\Support\Money;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $period_id
 * @property string|null $number
 * @property Carbon $entry_date
 * @property string|null $memo
 * @property string|null $source_type
 * @property int|null $source_id
 * @property JournalStatus $status
 * @property int|null $reversal_of_id
 * @property int|null $reversed_by_id
 * @property string|null $reversal_reason
 * @property Money $total_debits
 * @property Money $total_credits
 */
final class JournalEntry extends Model
{
    use BelongsToCompany;
    use HasDocumentMeta;

    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => JournalStatus::class,
            'checked_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'total_debits' => MoneyCast::class,
            'total_credits' => MoneyCast::class,
        ];
    }

    protected static function booted(): void
    {
        // Immutability (§16.1): a posted/reversed entry's accounting substance
        // never mutates. Only reversal-linkage fields may change (set when the
        // reversing entry is created). Corrections = reversal + new entry.
        self::updating(function (JournalEntry $entry): void {
            $original = $entry->getRawOriginal('status');

            if (! in_array($original, [JournalStatus::Posted->value, JournalStatus::Reversed->value], true)) {
                return;
            }

            $allowed = ['reversed_by_id', 'reversal_reason', 'status', 'updated_at'];

            foreach (array_keys($entry->getDirty()) as $attribute) {
                if (! in_array($attribute, $allowed, true)) {
                    throw ImmutableEntryException::make("attribute [{$attribute}]");
                }
            }
        });

        self::deleting(function (JournalEntry $entry): void {
            if (in_array($entry->status, [JournalStatus::Posted, JournalStatus::Reversed], true)) {
                throw ImmutableEntryException::make('posted entries cannot be deleted');
            }
        });
    }

    public function isPosted(): bool
    {
        return $this->status === JournalStatus::Posted;
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
