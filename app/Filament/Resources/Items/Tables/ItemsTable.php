<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Tables;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\AccountType;
use App\Enums\ItemType;
use App\Models\Account;
use App\Models\Item;
use App\Models\User;
use App\Support\Quantity;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('valuation'))
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('valuation.qty_units')
                    ->label('Qty on hand')
                    ->formatStateUsing(fn (int $state): string => rtrim(rtrim(Quantity::fromUnits($state), '0'), '.'))
                    ->placeholder('—'),
                TextColumn::make('valuation.avg_cost_x10000')
                    ->label('Avg cost')
                    ->formatStateUsing(fn (int $state): string => '₱'.number_format($state / 1_000_000, 2))
                    ->placeholder('—'),
                TextColumn::make('default_sales_price')
                    ->label('Sales price')
                    ->money('PHP', divideBy: 100)
                    ->sortable(),
                TextColumn::make('default_purchase_price')
                    ->label('Purchase price')
                    ->money('PHP', divideBy: 100)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('unit'),
                IconColumn::make('is_vat_exempt_item')
                    ->label('VAT-exempt')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('adjust')
                    ->label('Adjust')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (Item $record): bool => $record->type === ItemType::Inventory)
                    ->schema([
                        DatePicker::make('date')->default(now())->required(),
                        TextInput::make('qty_change')
                            ->label('Quantity change')
                            ->numeric()
                            ->required()
                            ->helperText('Positive to increase the count, negative to decrease.'),
                        Select::make('adjustment_account_id')
                            ->label('Adjustment account')
                            ->options(fn () => Account::query()
                                ->whereIn('type', [AccountType::Expense->value, AccountType::Income->value])
                                ->orderBy('code')
                                ->get()
                                ->mapWithKeys(fn (Account $a) => [$a->id => "{$a->code} — {$a->name}"]))
                            ->required(),
                        TextInput::make('unit_cost')
                            ->label('Unit cost (₱)')
                            ->numeric()
                            ->helperText('Increases only — leave blank to use the current average cost.'),
                        Textarea::make('reason'),
                    ])
                    ->action(function (Item $record, array $data): void {
                        /** @var User $user */
                        $user = Auth::user();

                        try {
                            app(AdjustInventory::class)->handle(
                                $record,
                                $data['date'],
                                (string) $data['qty_change'],
                                (int) $data['adjustment_account_id'],
                                filled($data['unit_cost'] ?? null) ? (int) round(((float) $data['unit_cost']) * 100) : null,
                                $data['reason'] ?? null,
                                $user,
                            );
                            Notification::make()->success()->title('Inventory adjusted')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not adjust inventory')->body($e->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
