<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BankStatementLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One imported bank statement line (§8). Amount is signed minor units:
 * positive = money into the account (deposit), negative = money out (charge).
 *
 * @property int $id
 * @property int $company_id
 * @property int $bank_account_id
 * @property Carbon $txn_date
 * @property string $description
 * @property int $amount
 * @property string $status
 * @property int|null $journal_entry_id
 */
final class BankStatementLine extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<BankStatementLineFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'txn_date' => 'date',
            'amount' => 'integer',
            'balance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
