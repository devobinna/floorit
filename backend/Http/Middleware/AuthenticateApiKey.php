<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = $request->header('X-API-Key');

        if (!$rawKey) {
            return apiResponse([], 'Missing API key. Provide the X-API-Key header.', false, 401);
        }

        // Cache resolved key data for 5 minutes (keyed by the raw key string)
        $cacheKey   = 'api_key_resolved:' . sha256($rawKey);
        $apiKeyData = Cache::get($cacheKey);

        if (!$apiKeyData) {
            // Direct plain-text lookup (keys are stored plain, not hashed)
            $apiKeyModel = ApiKey::where('key', $rawKey)
                ->where('is_active', true)
                ->with('user')
                ->first();

            if (!$apiKeyModel || $apiKeyModel->isExpired() || !$apiKeyModel->user) {
                return apiResponse([], 'Invalid or inactive API key.', false, 401);
            }

            $apiKeyData = [
                'id'         => $apiKeyModel->id,
                'user_id'    => $apiKeyModel->user_id,
                'rate_limit' => $apiKeyModel->rate_limit ?? 60,
                'user'       => $apiKeyModel->user,
            ];

            Cache::put($cacheKey, $apiKeyData, 300);
        }

        // Rate limiting (per-minute sliding window)
        $rateLimitKey = 'api_rate:' . $apiKeyData['id'] . ':' . now()->format('YmdHi');
        $requests     = (int) Cache::get($rateLimitKey, 0);

        if ($requests >= $apiKeyData['rate_limit']) {
            return apiResponse([], 'Rate limit exceeded. Try again in a moment.', false, 429);
        }

        Cache::put($rateLimitKey, $requests + 1, 90); // TTL > 1 min so it carries over naturally

        // Non-blocking usage tracking
        ApiKey::where('id', $apiKeyData['id'])->update([
            'last_used_at' => now(),
            'last_used_ip' => $request->ip(),
        ]);
        ApiKey::where('id', $apiKeyData['id'])->increment('total_requests');

        // Expose to controllers via request
        $request->merge([
            'api_key'  => $apiKeyData,
            'api_user' => $apiKeyData['user'],
        ]);

        return $next($request);
    }
}

// Helper used above
if (!function_exists('sha256')) {
    function sha256(string $value): string
    {
        return hash('sha256', $value);
    }
}
