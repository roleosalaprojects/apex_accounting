<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

abstract class ApiController extends Controller
{
    /**
     * Resolve the company from a payload id and assert the authenticated client
     * is a member (per-company membership, §2). Sets the active company context.
     */
    protected function authorizedCompany(int $companyId): Company
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->roleIn($companyId) === null) {
            throw new HttpResponseException(
                new JsonResponse(['message' => 'You do not belong to this company.'], 403)
            );
        }

        $company = Company::query()->withoutGlobalScopes()->findOrFail($companyId);
        app(CompanyContext::class)->set($company->id);

        return $company;
    }
}
