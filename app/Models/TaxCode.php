<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property int $rate_bp
 * @property string $kind
 */
final class TaxCode extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_bp' => 'integer',
        ];
    }

    public function isVat12(): bool
    {
        return $this->code === 'VAT12';
    }

    public function isExempt(): bool
    {
        return $this->code === 'EXEMPT';
    }

    public function isZeroRated(): bool
    {
        return $this->code === 'ZERO';
    }
}
