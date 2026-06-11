<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 2550Q working-paper record of a quarterly common input VAT allocation (§5.4).
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property int $quarter
 * @property Money $vatable_sales
 * @property Money $exempt_sales
 * @property Money $common_input_vat
 * @property int $ratio_creditable_bp
 * @property Money $creditable
 * @property Money $non_creditable
 * @property int|null $journal_entry_id
 */
final class VatAllocation extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'quarter' => 'integer',
            'ratio_creditable_bp' => 'integer',
            'vatable_sales' => MoneyCast::class,
            'exempt_sales' => MoneyCast::class,
            'common_input_vat' => MoneyCast::class,
            'creditable' => MoneyCast::class,
            'non_creditable' => MoneyCast::class,
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
