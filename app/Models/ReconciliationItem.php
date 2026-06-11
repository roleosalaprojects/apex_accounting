<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $reconciliation_id
 * @property int $journal_line_id
 * @property bool $is_cleared
 */
final class ReconciliationItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_cleared' => 'boolean'];
    }

    /**
     * @return BelongsTo<Reconciliation, $this>
     */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    /**
     * @return BelongsTo<JournalLine, $this>
     */
    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class);
    }
}
