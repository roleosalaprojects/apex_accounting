<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/** "Post" and "Reject" on a draft invoice or bill awaiting approval. */
final class ApprovalActions
{
    /**
     * @param  Closure(Model, User): Model  $post
     */
    public static function post(string $what, Closure $post): Action
    {
        return Action::make('post')
            ->label('Approve & post')->icon('heroicon-o-check-badge')->color('success')
            ->authorize('post')
            ->visible(fn (Model $record): bool => $record->getAttribute('status') === InvoiceStatus::Draft)
            ->requiresConfirmation()
            ->modalHeading("Approve and post this {$what}?")
            ->modalDescription('It gets its number, posts to the ledger and becomes immutable; the maker is told.')
            ->action(function (Model $record) use ($what, $post): void {
                /** @var User $user */
                $user = Auth::user();
                try {
                    $posted = $post($record, $user);
                    Notification::make()->success()->title(ucfirst($what).' posted as '.$posted->getAttribute('number'))->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title("Could not post {$what}")->body($e->getMessage())->send();
                }
            });
    }

    /**
     * @param  Closure(Model, string, User): void  $reject
     */
    public static function reject(string $what, Closure $reject, string $listUrl): Action
    {
        return Action::make('reject')
            ->label('Reject')->icon('heroicon-o-x-circle')->color('danger')
            ->authorize('reject')
            ->visible(fn (Model $record): bool => $record->getAttribute('status') === InvoiceStatus::Draft)
            ->modalHeading("Send this {$what} back?")
            ->modalDescription('The draft is removed and the maker gets your reason.')
            ->schema([Textarea::make('reason')->required()->minLength(5)->rows(3)])
            ->action(function (Model $record, array $data) use ($what, $reject, $listUrl) {
                /** @var User $user */
                $user = Auth::user();
                try {
                    $reject($record, $data['reason'], $user);
                    Notification::make()->success()->title(ucfirst($what).' rejected — the maker has been told')->send();

                    return redirect($listUrl);
                } catch (Throwable $e) {
                    Notification::make()->danger()->title("Could not reject {$what}")->body($e->getMessage())->send();

                    return null;
                }
            });
    }
}
