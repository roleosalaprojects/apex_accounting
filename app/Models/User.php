<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\CompanyRole;
use App\Support\Rbac\RbacRegistry;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Permission names held per company, memoized on this instance: policies
     * ask the same questions for every navigation item and table row of a
     * request. syncCompanyRole() / forgetCompanyRoles() reset it.
     *
     * @var array<int, array<string, true>>
     */
    private array $companyPermissionCache = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Resolve this user's role within a given company (source of truth: the
     * company_user pivot — roles are scoped per company, never global). (§2)
     */
    public function roleIn(int $companyId): ?CompanyRole
    {
        $company = $this->companies()->where('companies.id', $companyId)->first();

        if ($company === null) {
            return null;
        }

        $role = $company->getAttribute('pivot')->role;

        return CompanyRole::tryFrom((string) $role);
    }

    /**
     * Fine-grained authorization within a company (the spatie "team"). The
     * single chokepoint for permission checks — it verifies membership, scopes
     * the permission lookup to the company, and reads from the user's assigned
     * role(s) there. Used by Actions and Filament gates. (§16.10)
     */
    public function hasCompanyPermission(int $companyId, string $permission): bool
    {
        return isset($this->companyPermissions($companyId)[$permission]);
    }

    /**
     * @return array<string, true>
     */
    private function companyPermissions(int $companyId): array
    {
        if (isset($this->companyPermissionCache[$companyId])) {
            return $this->companyPermissionCache[$companyId];
        }

        if (! $this->companies()->whereKey($companyId)->exists()) {
            return $this->companyPermissionCache[$companyId] = [];
        }

        // Roles and direct permissions are team-scoped, so reload them for this
        // company. Load explicitly: getAllPermissions() reads the relation as a
        // property, which strict mode rejects on users hydrated in a batch.
        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
        $this->unsetRelation('roles')->unsetRelation('permissions')
            ->load(['permissions', 'roles.permissions']);

        $names = $this->getAllPermissions()
            ->where('guard_name', RbacRegistry::GUARD)
            ->pluck('name')
            ->all();

        return $this->companyPermissionCache[$companyId] = array_fill_keys($names, true);
    }

    /**
     * Assign (replacing any existing) the standard spatie role for this user
     * within the given company. Keeps the spatie assignment in step with the
     * company_user.role membership pivot.
     */
    public function syncCompanyRole(int $companyId, CompanyRole $role): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
        $this->unsetRelation('roles');
        $this->syncRoles([Role::findByName($role->value, RbacRegistry::GUARD)]);
        $this->companyPermissionCache = [];
    }

    /**
     * Drop all of this user's role assignments within a company (e.g. when they
     * are removed from the team).
     */
    public function forgetCompanyRoles(int $companyId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);
        $this->unsetRelation('roles');
        $this->syncRoles([]);
        $this->companyPermissionCache = [];
    }

    // --- Filament panel access + multi-company tenancy (§13) ---

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->companies()->exists();
    }

    /**
     * @return Collection<int, Company>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies()->whereKey($tenant->getKey())->exists();
    }
}
