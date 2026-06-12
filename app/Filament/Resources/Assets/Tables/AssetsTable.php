<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Tables;

use App\Actions\Assets\DisposeAsset;
use App\Actions\Assets\PlaceAssetInService;
use App\Enums\AccountSubtype;
use App\Enums\AssetStatus;
use App\Models\Account;
use App\Models\Asset;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('acquisition_date')->date()->sortable(),
                TextColumn::make('acquisition_cost')->label('Cost')->money('PHP', divideBy: 100),
                TextColumn::make('useful_life_months')->label('Life (mo)'),
                TextColumn::make('status')->badge(),
                TextColumn::make('in_service_date')->date()->label('In service'),
            ])
            ->recordActions([
                Action::make('placeInService')->label('Place in service')->icon('heroicon-o-play')
                    ->visible(fn (Asset $record): bool => $record->status === AssetStatus::Draft)
                    ->schema([DatePicker::make('in_service_date')->default(now())->required()])
                    ->action(function (Asset $record, array $data): void {
                        try {
                            app(PlaceAssetInService::class)->handle($record, $data['in_service_date']);
                            Notification::make()->success()->title('Asset placed in service')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Failed')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('dispose')->label('Dispose')->icon('heroicon-o-trash')->color('danger')
                    ->visible(fn (Asset $record): bool => in_array($record->status, [AssetStatus::InService, AssetStatus::FullyDepreciated], true))
                    ->schema([
                        DatePicker::make('date')->default(now())->required(),
                        TextInput::make('proceeds')->label('Proceeds (P)')->numeric()->default(0)->required(),
                        Select::make('deposit_to_account_id')->label('Deposit proceeds to')
                            ->options(fn () => Account::query()
                                ->whereIn('subtype', [AccountSubtype::Cash->value, AccountSubtype::Bank->value])
                                ->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                            ->required(),
                        Toggle::make('vatable')->label('VATable disposal (12% output VAT)')->default(false),
                    ])
                    ->action(function (Asset $record, array $data): void {
                        /** @var User $user */
                        $user = Auth::user();

                        try {
                            app(DisposeAsset::class)->handle(
                                $record,
                                $data['date'],
                                (int) round(((float) $data['proceeds']) * 100),
                                (int) $data['deposit_to_account_id'],
                                (bool) $data['vatable'],
                                $user,
                            );
                            Notification::make()->success()->title('Asset disposed')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Failed')->body($e->getMessage())->send();
                        }
                    }),
            ])
            ->defaultSort('acquisition_date', 'desc');
    }
}
