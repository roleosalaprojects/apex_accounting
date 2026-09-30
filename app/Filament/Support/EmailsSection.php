<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\SentEmail;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

/** The emails sent about a document or partner, newest first; hidden until there is one. */
final class EmailsSection
{
    public static function make(): Section
    {
        return Section::make('Emails sent')
            ->collapsible()
            ->visible(fn (Model $record): bool => $record->sentEmails()->exists())
            ->schema([
                RepeatableEntry::make('sentEmails')->hiddenLabel()->columns(4)
                    ->state(fn (Model $record) => $record->sentEmails()->with('user')->latest('sent_at')->limit(20)->get())
                    ->schema([
                        TextEntry::make('sent_at')->label('Sent')->dateTime('M j, Y g:i A'),
                        TextEntry::make('subject')->columnSpan(2),
                        TextEntry::make('recipients')->label('To')
                            ->formatStateUsing(fn (SentEmail $email): string => implode(', ', $email->recipients))
                            ->belowContent(fn (SentEmail $email): ?string => $email->user?->name === null ? null : 'by '.$email->user->name),
                    ]),
            ]);
    }
}
