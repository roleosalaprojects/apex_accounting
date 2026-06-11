<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $batch_id
 * @property int $row_no
 * @property string|null $column
 * @property string|null $value
 * @property string $message
 */
final class ImportError extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'row_no' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id');
    }
}
