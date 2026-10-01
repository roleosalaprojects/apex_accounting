<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
$owner = App\Models\User::query()->where('email', 'owner@apex.test')->firstOrFail();
foreach (array_slice($argv, 1) as $path) {
    Auth::guard('web')->login($owner);
    $response = $kernel->handle(Request::create($path));
    $error = $response->getStatusCode() >= 400 && isset($response->exception) ? ' '.substr($response->exception->getMessage(), 0, 200) : '';
    printf("%3d %s%s\n", $response->getStatusCode(), $path, $error);
}
