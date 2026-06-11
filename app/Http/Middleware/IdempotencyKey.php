<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey as IdempotencyKeyModel;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support (§14). Replays return the original result without
 * re-executing the operation. Keyed globally on the header value.
 */
final class IdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '') {
            return $next($request);
        }

        $existing = IdempotencyKeyModel::query()->where('key', $key)->first();
        if ($existing !== null) {
            return (new JsonResponse(
                json_decode($existing->response_body, true),
                $existing->response_status,
            ))->header('Idempotent-Replay', 'true');
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 300 && $response instanceof JsonResponse) {
            IdempotencyKeyModel::query()->create([
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
