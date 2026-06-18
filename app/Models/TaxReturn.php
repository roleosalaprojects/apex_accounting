<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaxReturnType;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TaxReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A persisted BIR return filing — a snapshot of the figures for a period. (§12)
 *
 * @property int $id
 * @property int $company_id
 * @property string $type
 * @property int $fiscal_year
 * @property int|null $quarter
 * @property array<string, mixed> $figures
 * @property string $status
 */
final class TaxReturn extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TaxReturnFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'quarter' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'figures' => 'array',
            'filed_at' => 'datetime',
        ];
    }

    public function returnType(): ?TaxReturnType
    {
        return TaxReturnType::tryFrom($this->type);
    }

    /** The headline amount (VAT payable / total EWT), in minor units. */
    public function headlineAmount(): int
    {
        $key = $this->returnType()?->headlineKey();

        return $key !== null ? (int) ($this->figures[$key] ?? 0) : 0;
    }
}
