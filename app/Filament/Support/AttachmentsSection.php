<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Attachment;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * Shared "Attachments" infolist section for document view pages — lists the
 * morphMany attachments() with a download link per file.
 */
final class AttachmentsSection
{
    public static function make(): Section
    {
        return Section::make('Attachments')
            ->collapsible()
            ->schema([
                RepeatableEntry::make('attachments')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('original_name')->label('File')
                        ->url(fn (Attachment $record): string => route('attachments.download', ['id' => $record->id]))
                        ->openUrlInNewTab(),
                    TextEntry::make('size')->label('Size')
                        ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 1).' KB'),
                    TextEntry::make('uploader.name')->label('Uploaded by')->placeholder('—'),
                    TextEntry::make('created_at')->label('Uploaded')->dateTime(),
                ])->placeholder('No attachments.'),
            ]);
    }
}
