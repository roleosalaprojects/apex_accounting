<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Rbac\RbacRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * Owner-only "Export company data" (§13): a ZIP of CSVs per table, logged to
 * audit_logs. Restores are an ops procedure, not a UI button.
 */
final class ExportCompanyData
{
    /** Tables exported (scoped by company_id). */
    private const TABLES = [
        'accounts', 'departments', 'projects', 'funds', 'branches',
        'accounting_periods', 'journal_entries', 'journal_lines', 'period_balances',
        'tax_codes', 'withholding_codes', 'vat_allocations',
        'customers', 'invoices', 'invoice_lines', 'customer_payments', 'payment_applications',
        'credit_memos', 'credit_memo_lines',
        'vendors', 'bills', 'bill_lines', 'vendor_payments', 'bill_applications', 'withholding_transactions',
        'bank_accounts', 'reconciliations',
        'items', 'item_valuations', 'inventory_adjustments',
        'asset_categories', 'assets', 'depreciation_entries',
        'recurring_templates', 'recurring_runs', 'audit_logs',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Company $company, User $actor, ?string $outputPath = null): string
    {
        if (! $actor->hasCompanyPermission($company->id, RbacRegistry::COMPANY_EXPORT)) {
            throw new RuntimeException('Only an owner may export company data.');
        }

        $path = $outputPath ?? tempnam(sys_get_temp_dir(), 'apex_export_').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create export archive.');
        }

        $manifest = [];
        foreach (self::TABLES as $table) {
            $rows = DB::table($table)->where('company_id', $company->id)->get();
            $zip->addFromString("{$table}.csv", $this->toCsv($rows));
            $manifest[$table] = $rows->count();
        }

        $zip->addFromString('manifest.json', (string) json_encode([
            'company' => $company->name,
            'tin' => $company->tin,
            'tables' => $manifest,
        ], JSON_PRETTY_PRINT));
        $zip->close();

        $this->audit->record($company->id, 'company.data_exported', $company, null, ['tables' => $manifest]);

        return $path;
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     */
    private function toCsv($rows): string
    {
        if ($rows->isEmpty()) {
            return '';
        }

        $handle = fopen('php://temp', 'r+');
        $first = (array) $rows->first();
        fputcsv($handle, array_keys($first), ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($v) => $v, (array) $row), ',', '"', '\\');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
