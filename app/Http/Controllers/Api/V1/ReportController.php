<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\Reports\TrialBalanceReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/reports/trial-balance (§14). Requires the reports:read scope.
 */
final class ReportController extends ApiController
{
    public function trialBalance(Request $request, TrialBalanceReport $report): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer'],
            'as_of' => ['required', 'date'],
        ]);

        $this->authorizedCompany((int) $validated['company_id']);

        return new JsonResponse($report->build((int) $validated['company_id'], $validated['as_of']));
    }
}
