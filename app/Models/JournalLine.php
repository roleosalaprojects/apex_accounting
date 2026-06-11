<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\JournalStatus;
use App\Enums\VatBucket;
use App\Exceptions\Ledger\ImmutableEntryException;
use App\Support\Money;
use Database\Factories\JournalLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $journal_entry_id
 * @property int $line_no
 * @property int $account_id
 * @property Money $debit
 * @property Money $credit
 * @property string|null $partner_type
 * @property int|null $partner_id
 * @property int|null $tax_code_id
 * @property VatBucket|null $vat_bucket
 * @property int|null $department_id
 * @property int|null $project_id
 * @property int|null $fund_id
 * @property int|null $branch_id
 * @property-read JournalEntry|null $journalEntry
 * @property-read Account|null $account
 */
final class JournalLine extends Model
{
    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'debit' => MoneyCast::class,
            'credit' => MoneyCast::class,
            'vat_bucket' => VatBucket::class,
        ];
    }

    protected static function booted(): void
    {
        // A posted entry's lines are immutable (§4.2). Guard mutation + deletion.
        self::saving(function (JournalLine $line): void {
            if ($line->exists && $line->journalEntry?->isPosted()) {
                throw ImmutableEntryException::make('lines of a posted entry');
            }
        });

        self::deleting(function (JournalLine $line): void {
            if ($line->journalEntry?->status === JournalStatus::Posted) {
                throw ImmutableEntryException::make('lines of a posted entry');
            }
        });
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function partner(): MorphTo
    {
        return $this->morphTo();
    }
}
