<?php
// Print every admin GET route as a concrete URL (real record ids), with a label, as JSON.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
$tables = [
    'accounts' => 'accounts', 'asset-categories' => 'asset_categories', 'assets' => 'assets', 'bank-accounts' => 'bank_accounts',
    'bills' => 'bills', 'branches' => 'branches', 'budgets' => 'budgets', 'customers' => 'customers', 'debit-memos' => 'debit_memos',
    'departments' => 'departments', 'document-sequences' => 'document_sequences', 'exchange-rates' => 'exchange_rates', 'funds' => 'funds',
    'invoices' => 'invoices', 'items' => 'items', 'journal-entries' => 'journal_entries', 'projects' => 'projects',
    'purchase-orders' => 'purchase_orders', 'reconciliations' => 'reconciliations', 'recurring-templates' => 'recurring_templates',
    'sales-orders' => 'sales_orders', 'tax-codes' => 'tax_codes', 'vendors' => 'vendors', 'withholding-codes' => 'withholding_codes',
];
$preferred = ['invoices' => ['status', 'posted'], 'bills' => ['status', 'posted'], 'sales-orders' => ['status', 'partially_invoiced'], 'journal-entries' => ['status', 'posted']];
$out = [];
foreach (Route::getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'admin')) continue;
    $uri = str_replace('{tenant}', '1', $route->uri());
    if (str_contains($uri, '{record}')) {
        $segment = explode('/', $route->uri())[2] ?? '';
        $table = $tables[$segment] ?? null;
        if ($table === null) continue;
        $q = DB::table($table)->where('company_id', 1);
        if (isset($preferred[$segment])) { $q2 = (clone $q)->where(...$preferred[$segment]); $id = $q2->orderByDesc('id')->value('id') ?? $q->orderByDesc('id')->value('id'); }
        else $id = $q->orderByDesc('id')->value('id');
        if ($id === null) continue;
        $uri = str_replace('{record}', (string) $id, $uri);
    }
    $out[] = ['uri' => '/'.$uri, 'name' => $route->getName()];
}
usort($out, fn ($a, $b) => strcmp($a['uri'], $b['uri']));
echo json_encode($out, JSON_PRETTY_PRINT);
