<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\LoginEvent;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Records login history + failed attempts — app-shell internal controls (§13).
 */
final class RecordLoginEvent
{
    public function handle(Login|Failed|Logout $event): void
    {
        $request = request();
        $user = $event->user; // Authenticatable|null (null only on Failed)

        LoginEvent::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'email' => $event instanceof Failed
                ? ($event->credentials['email'] ?? null)
                : $user?->getAttribute('email'),
            'result' => match (true) {
                $event instanceof Login => 'success',
                $event instanceof Logout => 'logout',
                default => 'failed',
            },
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
