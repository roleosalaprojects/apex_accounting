<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/accounts (§14). Requires the reports:read scope.
 */
final class AccountController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $companyId = (int) $request->validate(['company_id' => ['required', 'integer']])['company_id'];
        $this->authorizedCompany($companyId);

        $accounts = Account::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'subtype'])
            ->map(fn (Account $a) => [
                'id' => $a->id, 'code' => $a->code, 'name' => $a->name,
                'type' => $a->type->value, 'subtype' => $a->subtype->value,
            ]);

        return new JsonResponse(['data' => $accounts]);
    }
}
