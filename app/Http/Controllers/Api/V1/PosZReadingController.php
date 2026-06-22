<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Ledger\LedgerException;
use App\Models\User;
use App\Services\Integration\PosSalesMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * POST /api/v1/pos/z-readings — Apex POS end-of-day sales summary, mapped to a
 * balanced journal entry server-side (§14). Requires the pos:post scope.
 */
final class PosZReadingController extends ApiController
{
    public function store(Request $request, PosSalesMapper $mapper): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'business_date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
            'vatable_sales' => ['nullable', 'integer', 'min:0'],
            'exempt_sales' => ['nullable', 'integer', 'min:0'],
            'zero_rated_sales' => ['nullable', 'integer', 'min:0'],
            'vat_amount' => ['nullable', 'integer', 'min:0'],
            'discounts' => ['nullable', 'integer', 'min:0'],
            'tenders' => ['nullable', 'array'],
            'tenders.*' => ['integer', 'min:0'],
        ]);

        $company = $this->authorizedCompany((int) $validated['company_id']);

        /** @var User $user */
        $user = Auth::user();

        try {
            $entry = $mapper->post($company, $request->all(), $user);
        } catch (LedgerException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'id' => $entry->id,
            'number' => $entry->number,
            'status' => $entry->status->value,
            'total_debits' => $entry->total_debits->minor,
        ], 201);
    }
}
