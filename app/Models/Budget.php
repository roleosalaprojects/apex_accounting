<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An annual budget — a named set of per-account targets for a fiscal year,
 * compared against ledger actuals in the Budget vs Actual report. (§12)
 *
 * @property int $id
 * @property int $company_id
 * @property int $fiscal_year
 * @property string $name
 * @property string $status
 */
final class Budget extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
        ];
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
