<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $recurring_template_id
 * @property Carbon $ran_on
 * @property string|null $created_document_type
 * @property int|null $created_document_id
 * @property string $status
 * @property string|null $error
 */
final class RecurringRun extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['ran_on' => 'date'];
    }

    /**
     * @return BelongsTo<RecurringTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RecurringTemplate::class, 'recurring_template_id');
    }
}
