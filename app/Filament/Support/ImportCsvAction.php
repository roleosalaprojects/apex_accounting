<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Filament\Imports\CompanyImporter;
use Filament\Actions\ImportAction;

/**
 * "Import CSV" on a master-data list: upload, map the columns (an example
 * file is offered), and every row lands in the current company; rows that
 * fail come back as a CSV with the reason on each.
 */
final class ImportCsvAction
{
    /**
     * @param  class-string<CompanyImporter>  $importer
     */
    public static function make(string $importer, string $label): ImportAction
    {
        return ImportAction::make('import')
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->importer($importer)
            ->options(fn (): array => CompanyImporter::optionsFor())
            ->chunkSize(200);
    }
}
