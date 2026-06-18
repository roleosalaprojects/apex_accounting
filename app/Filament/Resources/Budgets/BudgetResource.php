<?php

declare(strict_types=1);

namespace App\Filament\Resources\Budgets;

use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\Budgets\Pages\ListBudgets;
use App\Filament\Resources\Budgets\Schemas\BudgetForm;
use App\Filament\Resources\Budgets\Tables\BudgetsTable;
use App\Models\Budget;
use App\Models\Company;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return BudgetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BudgetsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return self::userCanManageBudgets();
    }

    public static function canCreate(): bool
    {
        return self::userCanManageBudgets();
    }

    public static function canEdit(Model $record): bool
    {
        return self::userCanManageBudgets();
    }

    public static function canDelete(Model $record): bool
    {
        return self::userCanManageBudgets();
    }

    public static function userCanManageBudgets(): bool
    {
        $company = Filament::getTenant();
        $user = Auth::user();

        return $company instanceof Company && $user instanceof User
            && $user->hasCompanyPermission($company->id, RbacRegistry::BUDGET_MANAGE);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBudgets::route('/'),
            'create' => CreateBudget::route('/create'),
            'edit' => EditBudget::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
