<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Shared document fields (§2): internal/external references, remarks, and the
 * Prepared / Checked / Approved signatory trail rendered by print templates.
 *
 * Requires columns: reference_no, external_reference_no, remarks, created_by,
 * checked_by, checked_at, approved_by, approved_at.
 *
 * @phpstan-require-extends Model
 */
trait HasDocumentMeta
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
