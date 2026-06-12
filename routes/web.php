<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/attachments/{id}', function (int $id) {
    /** @var Attachment $attachment */
    $attachment = Attachment::query()->withoutGlobalScopes()->findOrFail($id);
    /** @var User|null $user */
    $user = Auth::user();

    abort_unless($user !== null && $user->companies()->whereKey($attachment->company_id)->exists(), 403);

    return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
})->middleware(['web', 'auth'])->name('attachments.download');
