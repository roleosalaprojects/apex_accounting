<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A dated FX rate: functional (PHP) per 1 unit of currency_code. (§17)
 *
 * @property int $id
 * @property int $company_id
 * @property string $currency_code
 * @property Carbon $rate_date
 * @property float $rate
 */
final class ExchangeRate extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate_date' => 'date',
            'rate' => 'float',
        ];
    }
}
