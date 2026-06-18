<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankStatementLines\Tables;

use App\Models\Account;
use App\Models\BankStatementLine;
use App\Services\Banking\BankStatementImporter;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Throwable;

class BankStatementLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('txn_date')->label('Date')->date()->sortable(),
                TextColumn::make('bankAccount.bank_name')->label('Bank')->toggleable(),
                TextColumn::make('description')->wrap()->limit(60)->searchable(),
                TextColumn::make('reference')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('amount')->label('Amount')->alignEnd()
                    ->state(fn (BankStatementLine $l): string => number_format($l->amount / 100, 2))
                    ->color(fn (BankStatementLine $l): string => $l->amount < 0 ? 'danger' : 'success'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'matched' => 'success',
                        'ignored' => 'gray',
                        default => 'warning',
                    }),
            ])
            ->defaultSort('txn_date', 'desc')
            ->filters([
                SelectFilter::make('status')->options([
                    'unmatched' => 'Unmatched',
                    'matched' => 'Matched',
                    'ignored' => 'Ignored',
                ]),
                SelectFilter::make('bank_account_id')->relationship('bankAccount', 'bank_name')->label('Bank account'),
            ])
            ->recordActions([
                Action::make('automatch')
                    ->label('Auto-match')->icon('heroicon-o-link')->color('info')
                    ->visible(fn (BankStatementLine $l): bool => $l->status === 'unmatched')
                    ->action(function (BankStatementLine $record): void {
                        $importer = app(BankStatementImporter::class);
                        $match = $importer->suggestMatches($record)->first();
                        if ($match === null) {
                            Notification::make()->warning()->title('No matching ledger entry found')->send();

                            return;
                        }
                        $importer->matchToEntry($record, $match);
                        Notification::make()->success()->title("Matched to {$match->number}")->send();
                    }),
                Action::make('post')
                    ->label('Post to ledger')->icon('heroicon-o-arrow-right-circle')->color('success')
                    ->visible(fn (BankStatementLine $l): bool => $l->status !== 'matched')
                    ->schema([
                        Select::make('contra_account_id')->label('Contra account')
                            ->options(fn (): array => Account::query()->orderBy('code')->get()
                                ->mapWithKeys(fn (Account $a): array => [$a->id => "{$a->code} — {$a->name}"])->all())
                            ->searchable()->required(),
                    ])
                    ->action(function (BankStatementLine $record, array $data): void {
                        try {
                            app(BankStatementImporter::class)->recordInLedger($record, (int) $data['contra_account_id'], Auth::user());
                            Notification::make()->success()->title('Posted to ledger')->send();
                        } catch (Throwable $e) {
                            Notification::make()->danger()->title('Could not post')->body($e->getMessage())->send();
                        }
                    }),
                Action::make('ignore')
                    ->label(fn (BankStatementLine $l): string => $l->status === 'ignored' ? 'Un-ignore' : 'Ignore')
                    ->icon('heroicon-o-eye-slash')->color('gray')
                    ->visible(fn (BankStatementLine $l): bool => $l->status !== 'matched')
                    ->action(fn (BankStatementLine $record) => $record->update([
                        'status' => $record->status === 'ignored' ? 'unmatched' : 'ignored',
                    ])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
