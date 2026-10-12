<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Gate untuk akses antar-server (project lain) tanpa login user / JWT.
 * Key dikirim lewat header "X-Api-Key" dan dicocokkan dengan INTEGRATION_API_KEYS.
 * Opsional: batasi IP lewat INTEGRATION_ALLOWED_IPS.
 */
class VerifyIntegrationKey
{
    public function handle(Request $request, Closure $next)
    {
        $allowedKeys = $this->parseList(config('services.integration.keys'));

        if (empty($allowedKeys)) {
            return response()->json(['message' => 'Integration API belum dikonfigurasi'], 503);
        }

        $key = (string) $request->header('X-Api-Key', '');
        $isValidKey = false;

        foreach ($allowedKeys as $validKey) {
            if ($key !== '' && hash_equals($validKey, $key)) {
                $isValidKey = true;
                break;
            }
        }

        if (!$isValidKey) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $allowedIps = $this->parseList(config('services.integration.allowed_ips'));

        if (!empty($allowedIps) && !in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }

    private function parseList($value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
