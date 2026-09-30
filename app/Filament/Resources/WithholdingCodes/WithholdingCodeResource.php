<?php

declare(strict_types=1);

namespace App\Filament\Resources\WithholdingCodes;

use App\Filament\Resources\WithholdingCodes\Pages\CreateWithholdingCode;
use App\Filament\Resources\WithholdingCodes\Pages\EditWithholdingCode;
use App\Filament\Resources\WithholdingCodes\Pages\ListWithholdingCodes;
use App\Filament\Support\PercentInput;
use App\Models\Company;
use App\Models\Vendor;
use App\Models\WithholdingCode;
use App\Models\WithholdingTransaction;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Expanded withholding tax codes: the BIR ATC (WC100, WI010 …), its rate and
 * whether it applies to purchases or sales. A code a vendor defaults to, or
 * that a payment has already withheld under, stays on the books.
 */
class WithholdingCodeResource extends Resource
{
    protected static ?string $model = WithholdingCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'EWT / ATC Codes';

    protected static ?string $modelLabel = 'EWT code';

    protected static ?string $pluralModelLabel = 'EWT / ATC codes';

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->maxLength(20)
                ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule): Unique {
                    /** @var Company $company */
                    $company = Filament::getTenant();

                    return $rule->where('company_id', $company->id);
                })
                ->helperText('Your own short code, e.g. WC100.'),
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('atc')->label('ATC')->required()->maxLength(20)
                ->helperText('The BIR alphanumeric tax code printed on the 2307 and the alphalist.'),
            PercentInput::make(),
            Select::make('applies_to')->label('Applies to')
                ->options(['purchase' => 'Purchases (we withhold from vendors)', 'sale' => 'Sales (customers withhold from us)'])
                ->default('purchase')->required()->native(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->fontFamily('mono')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('atc')->label('ATC')->fontFamily('mono'),
                TextColumn::make('rate_bp')->label('Rate')->alignEnd()
                    ->formatStateUsing(fn (int $state): string => PercentInput::format($state)),
                TextColumn::make('applies_to')->label('Applies to')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'sale' ? 'Sales' : 'Purchases'),
            ])
            ->defaultSort('code')
            ->paginated(false)
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    /** Vendors default to a code and payments withhold under one; either keeps it. */
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (Vendor::query()->where('default_withholding_code_id', $record->getKey())->exists()) {
            return Response::deny('A vendor defaults to this code.');
        }
        if (WithholdingTransaction::query()->where('withholding_code_id', $record->getKey())->exists()) {
            return Response::deny('Tax has already been withheld under this code.');
        }

        return parent::getDeleteAuthorizationResponse($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithholdingCodes::route('/'),
            'create' => CreateWithholdingCode::route('/create'),
            'edit' => EditWithholdingCode::route('/{record}/edit'),
        ];
    }
}
