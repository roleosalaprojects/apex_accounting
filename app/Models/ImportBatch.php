<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $kind
 * @property string $original_name
 * @property int $total_rows
 * @property int $ok_rows
 * @property int $failed_rows
 * @property string $status
 */
final class ImportBatch extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_rows' => 'integer',
            'ok_rows' => 'integer',
            'failed_rows' => 'integer',
        ];
    }

    /**
     * @return HasMany<ImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'batch_id');
    }
}
