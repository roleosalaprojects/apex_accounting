<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Rbac\RbacRegistry;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Provision the global permission catalog + standard roles so every
        // test can assign company roles and assert permission gates. Guarded
        // for tests that don't migrate a database (e.g. plain landing-page).
        if (Schema::hasTable('permissions')) {
            RbacRegistry::sync();
        }
    }
}
