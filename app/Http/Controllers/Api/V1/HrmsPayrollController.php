<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Ledger\LedgerException;
use App\Models\User;
use App\Services\Integration\HrmsPayrollMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * POST /api/v1/hrms/payroll — Charlie HRMS payroll summary, mapped to a balanced
 * journal entry server-side (§14). Requires the hrms:post scope.
 */
final class HrmsPayrollController extends ApiController
{
    public function store(Request $request, HrmsPayrollMapper $mapper): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'pay_date' => ['required', 'date'],
            'reference' => ['nullable', 'string'],
            'gross_pay' => ['required', 'integer', 'min:0'],
            'employer_contributions' => ['nullable', 'integer', 'min:0'],
            'withholding_tax' => ['nullable', 'integer', 'min:0'],
            'statutory_employee' => ['nullable', 'integer', 'min:0'],
            'net_pay' => ['required', 'integer', 'min:0'],
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
