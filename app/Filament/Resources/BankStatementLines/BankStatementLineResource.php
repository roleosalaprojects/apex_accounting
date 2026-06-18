<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankStatementLines;

use App\Filament\Resources\BankStatementLines\Pages\ListBankStatementLines;
use App\Filament\Resources\BankStatementLines\Tables\BankStatementLinesTable;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\User;
use App\Support\Rbac\RbacRegistry;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class BankStatementLineResource extends Resource
{
    protected static ?string $model = BankStatementLine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Banking';

    protected static ?string $navigationLabel = 'Bank Statements';

    public static function table(Table $table): Table
    {
        return BankStatementLinesTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        return self::userCanReconcile();
    }

    public static function userCanReconcile(): bool
    {
        $company = Filament::getTenant();
        $user = Auth::user();

        return $company instanceof Company && $user instanceof User
            && $user->hasCompanyPermission($company->id, RbacRegistry::BANK_RECONCILE);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankStatementLines::route('/'),
        ];
    }
}
