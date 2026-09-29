<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master data with a created_by column, picked from the company's members on
 * the form (App\Filament\Support\CreatedBySelect).
 *
 * @phpstan-require-extends Model
 */
trait HasCreator
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
