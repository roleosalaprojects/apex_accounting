<?php

declare(strict_types=1);

namespace App\Services\Numbering;

use App\Models\DocumentSequence;
use RuntimeException;

/**
 * Gapless-per-sequence document numbering (§16.8). The counter is incremented
 * under a row lock inside the caller's posting transaction, so concurrent
 * posts never collide or skip.
 */
final class NumberGenerator
{
    /**
     * Reserve and format the next number for a sequence key, e.g. JE-2026-000123.
     * MUST be called inside a DB transaction.
     */
    public function next(int $companyId, string $key, int $year): string
    {
        $sequence = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw new RuntimeException("No document sequence configured for [{$key}] on company {$companyId}.");
        }

        $number = $sequence->next_number;
        $sequence->next_number = $number + 1;
        $sequence->save();

        $body = str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT);
        $prefix = $sequence->prefix !== '' ? $sequence->prefix.'-' : '';

        return sprintf('%s%d-%s', $prefix, $year, $body);
    }
}
