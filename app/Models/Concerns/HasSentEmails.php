<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\SentEmail;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Documents and partners that emails are sent about. */
trait HasSentEmails
{
    /**
     * @return MorphMany<SentEmail, $this>
     */
    public function sentEmails(): MorphMany
    {
        return $this->morphMany(SentEmail::class, 'about');
    }
}
