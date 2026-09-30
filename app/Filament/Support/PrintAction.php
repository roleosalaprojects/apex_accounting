<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A "download this document as PDF" row or header action. */
final class PrintAction
{
    /**
     * @param  Closure(Model): string  $pdf  renders the PDF bytes
     * @param  Closure(Model): string  $filename
     */
    public static function make(string $name, string $label, Closure $pdf, Closure $filename): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(fn (Model $record): StreamedResponse => response()->streamDownload(
                function () use ($pdf, $record): void {
                    echo $pdf($record);
                },
                $filename($record),
            ));
    }
}
