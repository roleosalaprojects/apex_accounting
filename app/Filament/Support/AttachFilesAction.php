<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Company;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Shared "Attach files" header action for document view pages. Files are stored
 * on the local disk under attachments/{company} and recorded on the document's
 * morphMany attachments() relation (§13 source documents).
 */
final class AttachFilesAction
{
    public static function make(): Action
    {
        return Action::make('attach')
            ->label('Attach Files')
            ->icon('heroicon-o-paper-clip')
            ->schema([
                FileUpload::make('files')
                    ->label('Files')
                    ->multiple()
                    ->disk('local')
                    ->directory(function (): string {
                        /** @var Company $company */
                        $company = Filament::getTenant();

                        return "attachments/{$company->id}";
                    })
                    ->storeFileNamesIn('file_names')
                    ->maxSize(10_240)
                    ->required(),
            ])
            ->action(function (array $data, ViewRecord $livewire): void {
                /** @var Company $company */
                $company = Filament::getTenant();
                $record = $livewire->getRecord();
                $names = (array) ($data['file_names'] ?? []);

                foreach ((array) $data['files'] as $path) {
                    $record->attachments()->create([
                        'company_id' => $company->id,
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => $names[$path] ?? basename((string) $path),
                        'mime' => Storage::disk('local')->mimeType($path) ?: null,
                        'size' => Storage::disk('local')->size($path),
                        'uploaded_by' => Auth::id(),
                    ]);
                }

                Notification::make()->success()->title(count((array) $data['files']).' file(s) attached')->send();
            });
    }
}
