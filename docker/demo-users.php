<?php

declare(strict_types=1);

/*
 * Local demo logins for the Docker development container: one user per
 * standard role in the seeded demo company, all with the password "password".
 * Only ever run against the container's throwaway database.
 */

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$company = Company::query()->where('name', 'Dari Ventures Corp.')->firstOrFail();

echo "Demo logins for {$company->name}:\n";

foreach (CompanyRole::cases() as $role) {
    $user = User::query()->firstOrCreate(
        ['email' => "{$role->value}@apex.test"],
        ['name' => ucfirst($role->value), 'password' => 'password'],
    );

    $company->users()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    $user->syncCompanyRole($company->id, $role);

    echo "  {$user->email} / password\n";
}
