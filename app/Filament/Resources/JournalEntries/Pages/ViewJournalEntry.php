<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalEntries\Pages;

use App\Actions\Ledger\PostDraftJournalEntry;
use App\Actions\Ledger\ReverseJournalEntry;
use App\Enums\JournalStatus;
use App\Filament\Resources\JournalEntries\JournalEntryResource;
use App\Filament\Support\AttachFilesAction;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ViewJournalEntry extends ViewRecord
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AttachFilesAction::make(),
            Action::make('approvePost')->label('Approve & Post')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn (): bool => $this->record->status === JournalStatus::Draft && self::actorCanApprove())
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
                ->visible(fn (): bool => $this->record->status === JournalStatus::Posted && self::actorCanApprove())
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

    private static function actorCanApprove(): bool
    {
        /** @var Company|null $company */
        $company = Filament::getTenant();
        /** @var User|null $user */
        $user = Auth::user();

        return $company !== null && $user?->roleIn($company->id)?->canApprove() === true;
    }
}
