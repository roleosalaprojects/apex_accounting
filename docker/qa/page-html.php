<?php
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$owner = App\Models\User::query()->where('email', 'owner@apex.test')->firstOrFail();
Illuminate\Support\Facades\Auth::guard('web')->login($owner);
echo $kernel->handle(Illuminate\Http\Request::create($argv[1]))->getContent();
