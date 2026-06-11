<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\FundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Accounting dimension (§4.1).
 *
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
final class Fund extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<FundFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
