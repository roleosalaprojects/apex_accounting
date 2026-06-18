<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankStatementLines\Pages;

use App\Filament\Resources\BankStatementLines\BankStatementLineResource;
use App\Models\BankAccount;
use App\Services\Banking\BankStatementImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ListBankStatementLines extends ListRecords
{
    protected static string $resource = BankStatementLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import CSV')->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    Select::make('bank_account_id')->label('Bank account')
                        ->options(fn (): array => BankAccount::query()->get()
                            ->mapWithKeys(fn (BankAccount $b): array => [$b->id => trim("{$b->bank_name} {$b->account_no}")])->all())
                        ->required(),
                    FileUpload::make('file')->label('Statement CSV')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                        ->disk('local')->directory('bank-imports')->required(),
                ])
                ->action(function (array $data): void {
                    $bank = BankAccount::query()->findOrFail((int) $data['bank_account_id']);
                    $content = Storage::disk('local')->get($data['file']);

                    if ($content === null) {
                        Notification::make()->danger()->title('Could not read the uploaded file')->send();

                        return;
                    }

                    try {
                        $result = app(BankStatementImporter::class)->import($bank, $content, basename((string) $data['file']));
                        Notification::make()->success()
                            ->title("Imported {$result['imported']} line(s)")
                            ->body($result['skipped'] > 0 ? "{$result['skipped']} row(s) skipped." : null)
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Import failed')->body($e->getMessage())->send();
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }
                }),
        ];
    }
}
