<?php

declare(strict_types=1);

namespace App\Filament\Resources\RecurringTemplates;

use App\Enums\RecurringKind;
use App\Enums\RecurringSchedule;
use App\Filament\Resources\RecurringTemplates\Pages\CreateRecurringTemplate;
use App\Filament\Resources\RecurringTemplates\Pages\EditRecurringTemplate;
use App\Filament\Resources\RecurringTemplates\Pages\ListRecurringTemplates;
use App\Models\RecurringTemplate;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecurringTemplateResource extends Resource
{
    protected static ?string $model = RecurringTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|\UnitEnum|null $navigationGroup = 'Automation';

    protected static ?string $navigationLabel = 'Recurring Templates';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(160),
            Select::make('kind')->options(RecurringKind::class)->required()->live(),
            Select::make('schedule')->options(RecurringSchedule::class)->default('monthly')->required(),
            TextInput::make('day_of_month')->numeric()->integer()->default(1)->minValue(1)->maxValue(31)->required(),
            DatePicker::make('starts_on')->default(now())->required(),
            DatePicker::make('ends_on')->label('Ends on (optional)'),
            Toggle::make('auto_post')->label('Auto-post (otherwise drafts for approval)')->default(false),
            Toggle::make('is_active')->default(true),
            Textarea::make('payload')->label('Payload (JSON)')
                ->rows(8)->columnSpanFull()
                ->visible(fn (callable $get): bool => $get('kind') !== RecurringKind::DepreciationRun->value)
                ->helperText('Document body as JSON; amounts in centavos. For a journal entry: {"memo": "Monthly rent accrual", "lines": [{"account_id": 1, "debit": 5000000}, {"account_id": 2, "credit": 5000000}]}. For an invoice/bill: same fields as the API payload (customer_id/vendor_id, lines with qty, unit_price, tax_code_id, ...). The run date is filled in automatically.')
                ->rule('nullable')->rule('json'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('schedule')->badge(),
                TextColumn::make('next_run_on')->date()->sortable(),
                IconColumn::make('auto_post')->boolean(),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('runs_count')->counts('runs')->label('Runs'),
            ])
            ->defaultSort('next_run_on');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecurringTemplates::route('/'),
            'create' => CreateRecurringTemplate::route('/create'),
            'edit' => EditRecurringTemplate::route('/{record}/edit'),
        ];
    }
}
