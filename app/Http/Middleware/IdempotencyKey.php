<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\IdempotencyKey as IdempotencyKeyModel;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key support (§14). Replays return the original result without
 * re-executing the operation. Keyed per client (the token's user) and
 * bound to the request it answered: the same key with a different body is
 * refused rather than replayed.
 */
final class IdempotencyKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            return $next($request);
        }

        $userId = Auth::id();
        $hash = hash('sha256', $request->method().' '.$request->path().' '.$request->getContent());

        $existing = IdempotencyKeyModel::query()->where('user_id', $userId)->where('key', $key)->first();
        if ($existing !== null) {
            if ($existing->request_hash !== null && $existing->request_hash !== $hash) {
                return new JsonResponse(['message' => 'This Idempotency-Key was already used for a different request.'], 409);
            }

            return (new JsonResponse(
                json_decode($existing->response_body, true),
                $existing->response_status,
            ))->header('Idempotent-Replay', 'true');
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 300 && $response instanceof JsonResponse) {
            IdempotencyKeyModel::query()->create([
                'user_id' => $userId,
                'key' => $key,
                'method' => $request->method(),
                'path' => $request->path(),
                'request_hash' => $hash,
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'created_at' => now(),
            ]);
        }

        return $response;
    }
}
