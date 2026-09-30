<?php

declare(strict_types=1);

namespace App\Filament\Resources\TaxCodes;

use App\Filament\Resources\TaxCodes\Pages\EditTaxCode;
use App\Filament\Resources\TaxCodes\Pages\ListTaxCodes;
use App\Filament\Support\PercentInput;
use App\Models\TaxCode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The VAT codes every invoice and bill line carries: VAT 12%, VAT-exempt and
 * zero-rated. Posting keys on the codes themselves, so they can be renamed
 * and re-rated (should the statutory rate change) but not added or removed.
 */
class TaxCodeResource extends Resource
{
    protected static ?string $model = TaxCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'VAT Codes';

    protected static ?string $modelLabel = 'VAT code';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->disabled()->dehydrated(false),
            TextInput::make('name')->required()->maxLength(100),
            PercentInput::make()
                ->helperText('The rate applied to every line that carries this code.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->fontFamily('mono'),
                TextColumn::make('name'),
                TextColumn::make('rate_bp')->label('Rate')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => PercentInput::format($state)),
                TextColumn::make('kind')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'output' ? 'Output VAT' : 'Input-capable'),
            ])
            ->defaultSort('code')
            ->paginated(false)
            ->recordActions([EditAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxCodes::route('/'),
            'edit' => EditTaxCode::route('/{record}/edit'),
        ];
    }
}
