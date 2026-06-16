<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Provision the fine-grained RBAC catalog (permissions + standard roles) and
 * back-fill spatie role assignments from the existing company_user.role pivot,
 * so the membership pivot and the spatie team-scoped roles start in lock-step.
 */
return new class extends Migration
{
    public function up(): void
    {
        RbacRegistry::sync();

        foreach (DB::table('company_user')->get() as $membership) {
            $role = CompanyRole::tryFrom((string) $membership->role);
            $user = User::query()->find($membership->user_id);

            if ($role !== null && $user !== null) {
                $user->syncCompanyRole((int) $membership->company_id, $role);
            }
        }
    }

    public function down(): void
    {
        // Role assignments live in spatie tables dropped by their own migration.
    }
};
