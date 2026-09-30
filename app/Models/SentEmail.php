<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One email that left the system: what was sent, about which document, to
 * whom, by whom and when.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string|null $about_type
 * @property int|null $about_id
 * @property string $mailable
 * @property string $subject
 * @property list<string> $recipients
 * @property Carbon $sent_at
 */
final class SentEmail extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recipients' => 'array', 'sent_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function about(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
