<?php

declare(strict_types=1);

use App\Support\Rbac\RbacRegistry;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-provision the RBAC catalog so existing deployments pick up newly added
 * permissions (e.g. budget.manage). Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        RbacRegistry::sync();
    }

    public function down(): void
    {
        //
    }
};
