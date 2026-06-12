<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Read-only audit trail viewer (§13). Entries are written by AuditLogger from
 * the Actions layer and are never editable from the UI.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Audit Log';

    public static function canViewAny(): bool
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();
        /** @var User|null $user */
        $user = Auth::user();

        return $company !== null && $user?->roleIn($company->id)?->canManageCompany() === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('User')->placeholder('System')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('auditable_type')->label('Record')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->description(fn (AuditLog $record): ?string => $record->auditable_id !== null ? "#{$record->auditable_id}" : null),
                TextColumn::make('reason')->limit(60)->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
