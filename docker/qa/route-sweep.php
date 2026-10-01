<?php
// Sweep every admin GET route as a given demo user, substituting real record ids.
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

$email = $argv[1] ?? 'owner@apex.test';
$user = App\Models\User::query()->where('email', $email)->firstOrFail();
$tables = [
    'accounts' => 'accounts', 'asset-categories' => 'asset_categories', 'assets' => 'assets', 'bank-accounts' => 'bank_accounts',
    'bills' => 'bills', 'branches' => 'branches', 'budgets' => 'budgets', 'customers' => 'customers', 'debit-memos' => 'debit_memos',
    'departments' => 'departments', 'document-sequences' => 'document_sequences', 'exchange-rates' => 'exchange_rates', 'funds' => 'funds',
    'invoices' => 'invoices', 'items' => 'items', 'journal-entries' => 'journal_entries', 'projects' => 'projects',
    'purchase-orders' => 'purchase_orders', 'reconciliations' => 'reconciliations', 'recurring-templates' => 'recurring_templates',
    'sales-orders' => 'sales_orders', 'tax-codes' => 'tax_codes', 'withholding-codes' => 'withholding_codes',
];
$results = ['ok' => 0, 'forbidden' => [], 'errors' => [], 'skipped' => []];
foreach (Route::getRoutes() as $route) {
    if (! in_array('GET', $route->methods(), true) || ! str_starts_with($route->uri(), 'admin/{tenant}')) continue;
    $uri = str_replace('{tenant}', '1', $route->uri());
    if (str_contains($uri, '{record}')) {
        $segment = explode('/', $route->uri())[2] ?? '';
        $table = $tables[$segment] ?? null;
        $id = $table ? DB::table($table)->where('company_id', 1)->orderByDesc('id')->value('id') : null;
        if ($id === null) { $results['skipped'][] = $uri; continue; }
        $uri = str_replace('{record}', (string) $id, $uri);
    }
    Auth::guard('web')->login($user);
    try {
        $response = $kernel->handle(Request::create('/'.$uri));
        $code = $response->getStatusCode();
    } catch (Throwable $e) {
        $code = 500; $response = null; $msg = $e->getMessage();
    }
    if ($code === 200) $results['ok']++;
    elseif ($code === 403) $results['forbidden'][] = $uri;
    elseif ($code === 302) $results['ok']++;
    else $results['errors'][] = [$code, $uri, substr($msg ?? ($response?->exception?->getMessage() ?? ''), 0, 160)];
}
printf("%s: %d ok, %d forbidden, %d errors, %d skipped\n", $email, $results['ok'], count($results['forbidden']), count($results['errors']), count($results['skipped']));
foreach ($results['errors'] as [$code, $uri, $msg]) printf("  %d %s %s\n", $code, $uri, $msg);
foreach ($results['forbidden'] as $uri) printf("  403 %s\n", $uri);
foreach ($results['skipped'] as $uri) printf("  skip %s\n", $uri);
