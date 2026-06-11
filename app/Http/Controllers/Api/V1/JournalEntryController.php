<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ledger\PostJournalEntry;
use App\Data\Ledger\JournalEntryData;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * POST /api/v1/journal-entries — Apex POS end-of-day Z-reading + Charlie HRMS
 * payroll summary JE (§14). Requires the je:post scope.
 */
final class JournalEntryController extends ApiController
{
    public function store(Request $request, PostJournalEntry $post): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'entry_date' => ['required', 'date'],
            'memo' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer'],
        ]);

        $this->authorizedCompany((int) $validated['company_id']);

        /** @var User $user */
        $user = Auth::user();
        $entry = $post->handle(JournalEntryData::from($request->all()), $user);

        return new JsonResponse([
            'id' => $entry->id,
            'number' => $entry->number,
            'status' => $entry->status->value,
            'total_debits' => $entry->total_debits->minor,
        ], 201);
    }
}
