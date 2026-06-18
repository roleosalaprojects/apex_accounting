<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxReturns;

use App\Filament\Resources\TaxReturns\Pages\CreateTaxReturn;
use App\Filament\Resources\TaxReturns\Pages\ListTaxReturns;
use App\Filament\Resources\TaxReturns\Schemas\TaxReturnForm;
use App\Filament\Resources\TaxReturns\Tables\TaxReturnsTable;
use App\Models\Company;
use App\Models\TaxReturn;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TaxReturnResource extends Resource
{
    protected static ?string $model = TaxReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Tax Returns';

    public static function form(Schema $schema): Schema
    {
        return TaxReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxReturnsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return self::userCanManage();
    }

    public static function canCreate(): bool
    {
        return self::userCanManage();
    }

    public static function canDelete(Model $record): bool
    {
        return self::userCanManage();
    }

    public static function userCanManage(): bool
    {
        $company = Filament::getTenant();
        $user = Auth::user();

        return $company instanceof Company && $user instanceof User
            && $user->hasCompanyPermission($company->id, RbacRegistry::TAX_RETURNS_MANAGE);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxReturns::route('/'),
            'create' => CreateTaxReturn::route('/create'),
        ];
    }
}
