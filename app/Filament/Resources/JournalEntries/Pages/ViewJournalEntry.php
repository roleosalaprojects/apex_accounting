<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Actions\Ledger\PostDraftJournalEntry;
use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\JournalStatus;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\AttachFilesAction;
use App\Filament\Support\LoadsRecordRelations;
use App\Models\JournalEntry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ViewJournalEntry extends ViewRecord
{
    use LoadsRecordRelations;

    protected static string $resource = JournalEntryResource::class;

    protected function recordRelations(): array
    {
        return [
            'lines.account', 'lines.department', 'lines.project', 'lines.fund', 'lines.branch',
            'reversalOf', 'reversedBy', 'attachments.uploader',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            AttachFilesAction::make(),
            Action::make('approvePost')->label('Approve & Post')->icon('heroicon-o-check-circle')->color('success')
                ->authorize('approveAndPost')
                ->visible(fn (): bool => $this->record->status === JournalStatus::Draft)
                ->requiresConfirmation()
                ->action(function (): void {
                    /** @var JournalEntry $entry */
                    $entry = $this->record;
                    /** @var User $user */
                    $user = Auth::user();

                    try {
                        $posted = app(PostDraftJournalEntry::class)->handle($entry, $user);
                        Notification::make()->success()->title("Posted as {$posted->number}")->send();
                        $this->redirect(JournalEntryResource::getUrl('view', ['record' => $posted]));
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not post')->body($e->getMessage())->send();
                    }
                }),
            Action::make('reverse')->label('Reverse')->icon('heroicon-o-arrow-uturn-left')->color('danger')
                ->authorize('reverse')
                ->visible(fn (): bool => $this->record->status === JournalStatus::Posted)
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')->required()->minLength(5),
                    DatePicker::make('reversal_date')->label('Reversal date (defaults to original date)'),
                ])
                ->action(function (array $data): void {
                    /** @var JournalEntry $entry */
                    $entry = $this->record;
                    /** @var User $user */
                    $user = Auth::user();

                    try {
                        $reversal = app(ReverseJournalEntry::class)->handle($entry, $data['reason'], $data['reversal_date'] ?? null, $user);
                        Notification::make()->success()->title("Reversed by {$reversal->number}")->send();
                    } catch (Throwable $e) {
                        Notification::make()->danger()->title('Could not reverse')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
