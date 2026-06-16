<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

/**
 * Per-company membership and roles (§2 — roles live on the company_user pivot,
 * never globally). Owner/Accountant approve+post; Bookkeeper drafts; Viewer reads.
 */
class Team extends Page
{
    protected string $view = 'filament.pages.team';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function canAccess(): bool
    {
        return self::userCanManageTeam();
    }

    public static function userCanManageTeam(): bool
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();
        /** @var User|null $user */
        $user = Auth::user();

        return $company !== null && $user?->hasCompanyPermission($company->id, RbacRegistry::USERS_MANAGE) === true;
    }

    /**
     * @return Collection<int, User>
     */
    public function members(): Collection
    {
        /** @var Company $company */
        $company = Filament::getTenant();

        return $company->users()->orderBy('name')->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addMember')->label('Add Member')->icon('heroicon-o-user-plus')
                ->schema([
                    TextInput::make('email')->email()->required(),
                    TextInput::make('name')->helperText('Used only when the email is new — a user account is created.'),
                    TextInput::make('password')->password()->revealable()
                        ->helperText('Required for new users; ignored for existing ones.'),
                    Select::make('role')->options(self::roleOptions())->default(CompanyRole::Viewer->value)->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();

                    $user = User::query()->where('email', $data['email'])->first();

                    if ($user === null) {
                        if (blank($data['name'] ?? null) || blank($data['password'] ?? null)) {
                            Notification::make()->danger()->title('Could not add member')
                                ->body('No user with that email exists — provide a name and password to create one.')->send();

                            return;
                        }

                        $user = User::query()->create([
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'password' => Hash::make($data['password']),
                        ]);
                    }

                    if ($company->users()->whereKey($user->id)->exists()) {
                        Notification::make()->warning()->title("{$user->name} is already a member")->send();

                        return;
                    }

                    $company->users()->attach($user->id, ['role' => $data['role']]);
                    $user->syncCompanyRole($company->id, CompanyRole::from($data['role']));
                    Notification::make()->success()->title("{$user->name} added as {$data['role']}")->send();
                }),
            Action::make('changeRole')->label('Change Role')->icon('heroicon-o-arrows-right-left')
                ->schema(fn (): array => [
                    Select::make('user_id')->label('Member')->options($this->memberOptions())->required(),
                    Select::make('role')->options(self::roleOptions())->required(),
                ])
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    $userId = (int) $data['user_id'];

                    if ($data['role'] !== CompanyRole::Owner->value && $this->isLastOwner($company, $userId)) {
                        Notification::make()->danger()->title('A company must keep at least one owner')->send();

                        return;
                    }

                    $company->users()->updateExistingPivot($userId, ['role' => $data['role']]);
                    User::query()->whereKey($userId)->first()?->syncCompanyRole($company->id, CompanyRole::from($data['role']));
                    Notification::make()->success()->title('Role updated')->send();
                }),
            Action::make('removeMember')->label('Remove Member')->icon('heroicon-o-user-minus')->color('danger')
                ->schema(fn (): array => [
                    Select::make('user_id')->label('Member')->options($this->memberOptions())->required(),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    /** @var Company $company */
                    $company = Filament::getTenant();
                    $userId = (int) $data['user_id'];

                    if ($this->isLastOwner($company, $userId)) {
                        Notification::make()->danger()->title('A company must keep at least one owner')->send();

                        return;
                    }

                    User::query()->whereKey($userId)->first()?->forgetCompanyRoles($company->id);
                    $company->users()->detach($userId);
                    Notification::make()->success()->title('Member removed')->send();
                }),
        ];
    }

    private function isLastOwner(Company $company, int $userId): bool
    {
        $owners = $company->users()->wherePivot('role', CompanyRole::Owner->value)->pluck('users.id');

        return $owners->count() === 1 && (int) $owners->first() === $userId;
    }

    /**
     * @return array<int, string>
     */
    private function memberOptions(): array
    {
        return $this->members()->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"])->all();
    }

    /**
     * @return array<string, string>
     */
    private static function roleOptions(): array
    {
        return collect(CompanyRole::cases())
            ->mapWithKeys(fn (CompanyRole $r) => [$r->value => ucfirst($r->value)])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'members' => $this->members(),
        ];
    }
}
