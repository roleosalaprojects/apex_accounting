<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PosZReadingStatus;
use App\Models\PosZReading;
use App\Models\User;
use App\Services\Integration\PosSalesMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * POST /api/v1/pos/z-readings — Apex POS end-of-day sales summary. The reading is
 * staged in the integration inbox (§14), NOT posted: apex_accounting is a separate
 * system, so an admin decides which readings to import (each becoming a draft
 * journal entry) rather than POS writing to the ledger automatically. Requires the
 * pos:post scope. An inconsistent (unbalanced) reading is rejected up front.
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

        [$debits, $credits] = $mapper->totals($request->all());
        if ($debits === 0 || $debits !== $credits) {
            return new JsonResponse(['message' => "Z-reading does not balance: tenders+discounts {$debits} != sales+VAT {$credits}."], 422);
        }

        $reading = new PosZReading;
        $reading->forceFill([
            'company_id' => $company->id,
            'business_date' => $validated['business_date'],
            'reference' => $validated['reference'] ?? null,
            'vatable_sales' => (int) ($validated['vatable_sales'] ?? 0),
            'exempt_sales' => (int) ($validated['exempt_sales'] ?? 0),
            'zero_rated_sales' => (int) ($validated['zero_rated_sales'] ?? 0),
            'vat_amount' => (int) ($validated['vat_amount'] ?? 0),
            'discounts' => (int) ($validated['discounts'] ?? 0),
            'tenders' => $validated['tenders'] ?? [],
            'status' => PosZReadingStatus::Pending,
            'created_by' => $user->id,
        ]);
        $reading->save();

        return new JsonResponse([
            'id' => $reading->id,
            'status' => $reading->status->value,
            'business_date' => $reading->business_date->toDateString(),
            'reference' => $reading->reference,
            'total_debits' => $debits,
            'message' => 'Z-reading received and awaiting import.',
        ], 201);
    }
}
