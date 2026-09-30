<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\SentEmail;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * "Email …" on a document or partner page: recipients, subject and message
 * prefilled, sent through DocumentMailer. $defaults gives the prefill for a
 * record; $send does the sending with the form's data.
 */
final class EmailAction
{
    /**
     * @param  Closure(Model): array{to: list<string>, subject: string, message: string}  $defaults
     * @param  Closure(Model, array<string, mixed>, User): SentEmail  $send
     */
    public static function make(string $name, string $label, Closure $defaults, Closure $send): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->modalHeading($label)
            ->modalSubmitActionLabel('Send')
            ->fillForm(fn (Model $record): array => $defaults($record))
            ->schema([
                TagsInput::make('to')->label('To')
                    ->placeholder('Type an address and press Enter')
                    ->nestedRecursiveRules(['email'])
                    ->required(),
                TextInput::make('subject')->required()->maxLength(200),
                Textarea::make('message')->rows(8)->required(),
            ])
            ->action(function (Model $record, array $data) use ($send): void {
                /** @var User $user */
                $user = Auth::user();

                try {
                    $sent = $send($record, $data, $user);
                    Notification::make()->success()->title('Emailed to '.implode(', ', $sent->recipients))->send();
                } catch (Throwable $e) {
                    Notification::make()->danger()->title('Could not send')->body($e->getMessage())->send();
                }
            });
    }
}
