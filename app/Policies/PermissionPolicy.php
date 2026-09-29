<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps Filament's resource abilities onto the company-scoped permission catalog
 * (RbacRegistry). The panel runs in strict authorization mode, so every
 * resource model needs a policy — a resource without one fails loudly instead
 * of silently allowing every member.
 *
 * Checks run in the record's own company, else the active Filament tenant; with
 * neither (console, API, guests) every ability is denied.
 */
abstract class PermissionPolicy
{
    /** @var list<string> Any one of these lets a member see the records. */
    protected array $viewPermissions = [];

    /** @var list<string> Every one of these is needed to create a record; empty means nobody. */
    protected array $createPermissions = [];

    /** @var list<string> Every one of these is needed to edit, delete or restore a record; empty means nobody. */
    protected array $managePermissions = [];

    public function viewAny(User $user): bool
    {
        return $this->allowsAny($user, $this->viewPermissions);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allowsAny($user, $this->viewPermissions, $record);
    }

    public function create(User $user): bool
    {
        return $this->allowsAll($user, $this->createPermissions);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allowsAll($user, $this->managePermissions, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allowsAll($user, $this->managePermissions, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->allowsAll($user, $this->managePermissions);
    }

    public function restore(User $user, Model $record): bool
    {
        return $this->allowsAll($user, $this->managePermissions, $record);
    }

    public function restoreAny(User $user): bool
    {
        return $this->allowsAll($user, $this->managePermissions);
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $this->allowsAll($user, $this->managePermissions, $record);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->allowsAll($user, $this->managePermissions);
    }

    public function replicate(User $user, Model $record): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function allowsAny(User $user, array $permissions, ?Model $record = null): bool
    {
        $companyId = $this->companyId($record);

        if ($companyId === null) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($user->hasCompanyPermission($companyId, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function allowsAll(User $user, array $permissions, ?Model $record = null): bool
    {
        $companyId = $this->companyId($record);

        if ($companyId === null || $permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $user->hasCompanyPermission($companyId, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function companyId(?Model $record): ?int
    {
        $companyId = $record?->getAttribute('company_id');

        if (is_int($companyId) || (is_string($companyId) && ctype_digit($companyId))) {
            return (int) $companyId;
        }

        $tenant = Filament::getTenant();

        return $tenant instanceof Company ? $tenant->id : null;
    }
}
