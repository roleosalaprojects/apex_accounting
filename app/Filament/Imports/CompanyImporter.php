<?php

declare(strict_types=1);

namespace App\Filament\Imports;

use App\Models\Account;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;

/**
 * Base for the onboarding importers: every row belongs to the company the
 * import was started in (passed as an option, since the job runs outside
 * the tenant request), records are matched on their code so a re-import
 * updates instead of duplicating, and account codes resolve within the
 * company or fail the row with a readable reason.
 */
abstract class CompanyImporter extends Importer
{
    /** The option every import carries: which company the rows belong to. */
    public static function optionsFor(): array
    {
        return ['company_id' => Filament::getTenant()?->getKey()];
    }

    protected function companyId(): int
    {
        return (int) ($this->options['company_id'] ?? 0);
    }

    /** Pesos as typed ("1,250.50") to centavos. */
    protected static function pesosToMinor(mixed $state): ?int
    {
        if (blank($state)) {
            return null;
        }
        $clean = str_replace([',', '₱', ' '], '', (string) $state);
        if (! is_numeric($clean)) {
            throw new RowImportFailedException("'{$state}' is not an amount.");
        }

        return (int) round(((float) $clean) * 100);
    }

    /** An account code ("4100") to its id within the company, or a readable failure. */
    protected function accountId(mixed $code, string $label): ?int
    {
        if (blank($code)) {
            return null;
        }
        $id = Account::query()->withoutGlobalScopes()
            ->where('company_id', $this->companyId())->where('code', trim((string) $code))->value('id');
        if ($id === null) {
            throw new RowImportFailedException("{$label}: no account with code '{$code}'.");
        }

        return (int) $id;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Import finished: '.number_format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';
        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' '.number_format($failed).' '.str('row')->plural($failed).' failed — download the failed rows to see why.';
        }

        return $body;
    }
}
