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
 * @property string $atc
 * @property string $applies_to
 */
final class WithholdingCode extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_bp' => 'integer',
        ];
    }
}
