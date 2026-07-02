<?php

namespace App\Http\Controllers\Api;

use App\Generation\Exceptions\GenerationException;
use App\Generation\GenerationContext;
use App\Generation\GenerationOrchestrator;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Generation;
use App\Models\Texture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SDK / Embed generation endpoints.
 * Auth: Bearer token, api_key field (POST body or query param) — all resolve to api_keys.key.
 */
class EmbedController extends Controller
{
    public function __construct(private GenerationOrchestrator $orchestrator) {}

    // ─── Auth helpers ─────────────────────────────────────────────────────────

    private function resolveToken(Request $request): ?string
    {
        // Bearer header (primary)
        $bearer = $request->bearerToken();
        if ($bearer) return $bearer;

        // Authorization header fallback (Apache sometimes strips bearer)
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) return substr($authHeader, 7);

        // Apache/Nginx rewrite headers
        $rewrite = $request->header('X-Authorization')
                ?? $request->server('HTTP_AUTHORIZATION')
                ?? $request->server('REDIRECT_HTTP_AUTHORIZATION');
        if ($rewrite && str_starts_with($rewrite, 'Bearer ')) return substr($rewrite, 7);
        if ($rewrite) return $rewrite;

        // Body / query fallback (embed_token or api_key)
        if ($request->filled('embed_token')) return $request->input('embed_token');
        if ($request->filled('api_key'))     return $request->input('api_key');
        if ($request->query('api_key'))      return $request->query('api_key');

        return null;
    }

    private function findApiKey(?string $token): ?ApiKey
    {
        if (!$token) return null;

        return ApiKey::where('key', $token)
            ->where('is_active', true)
            ->with('user')
            ->first();
    }

    // ─── Endpoints ────────────────────────────────────────────────────────────

    /**
     * POST /api/embed/validate-key
     */
    public function validateKey(Request $request)
    {
        $apiKey = $this->findApiKey($this->resolveToken($request));

        if (!$apiKey || !$apiKey->isValid()) {
            return apiResponse(['valid' => false], 'Invalid or inactive API key.', false, 401);
        }

        $apiKey->incrementUsage();

        return apiResponse([
            'valid'   => true,
            'user'    => [
                'name'    => $apiKey->user?->fullname ?? 'Floorit User',
                'email'   => $apiKey->user?->email,
                'credits' => $apiKey->user?->credits ?? 0,
            ],
        ], 'Valid API key.');
    }

    /**
     * GET /api/embed/textures
     */
    public function textures(Request $request)
    {
        $apiKey = $this->findApiKey($this->resolveToken($request));

        if (!$apiKey || !$apiKey->isValid()) {
            return apiResponse([], 'Invalid API key.', false, 401);
        }

        $textures = Texture::active()->ordered()->get()->map(fn ($t) => [
            'id'            => $t->id,
            'name'          => $t->name,
            'category'      => $t->category,
            'thumbnail_url' => $t->thumbnail_path ? asset($t->thumbnail_path) : asset($t->file_path),
        ]);

        return apiResponse($textures->values()->all(), 'Textures retrieved.');
    }

    /**
     * POST /api/embed/generate
     */
    public function generate(Request $request)
    {
        $request->validate([
            'texture_id' => 'required|integer|exists:textures,id',
            'image'      => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
            'room_type'  => 'nullable|string|max:100',
        ]);

        $apiKey = $this->findApiKey($this->resolveToken($request));

        if (!$apiKey || !$apiKey->isValid()) {
            return apiResponse([], 'Invalid or inactive API key.', false, 401);
        }

        $context            = new GenerationContext('sdk');
        $context->user      = $apiKey->user;
        $context->imageFile = $request->file('image');
        $context->textureId = (int) $request->texture_id;
        $context->roomType  = $request->room_type;
        $context->guestIp   = $request->ip();

        try {
            $generation = $this->orchestrator->handle($context);

            $apiKey->incrementUsage();

            return apiResponse([
                'uuid'   => $generation->uuid,
                'status' => $generation->status,
            ], 'Generation submitted successfully.', true, 202);

        } catch (GenerationException $e) {
            return apiResponse([], $e->getMessage(), false, 422);
        } catch (\Throwable $e) {
            Log::error('Embed generation failed', ['error' => $e->getMessage()]);
            return apiResponse([], 'Internal server error.', false, 500);
        }
    }

    /**
     * GET /api/embed/status/{uuid}
     * Also aliased to GET /api/embed/generation/{uuid} for SDK backwards compat.
     */
    public function status(Request $request, string $uuid)
    {
        $apiKey = $this->findApiKey($this->resolveToken($request));

        if (!$apiKey || !$apiKey->isValid()) {
            return apiResponse([], 'Invalid API key.', false, 401);
        }

        $generation = Generation::where('uuid', $uuid)
            ->where('user_id', $apiKey->user_id)
            ->first();

        if (!$generation) {
            return apiResponse([], 'Generation not found.', false, 404);
        }

        $progress = match ($generation->status) {
            'pending'    => 20,
            'processing' => 70,
            'completed'  => 100,
            default      => 0,
        };

        return apiResponse([
            'uuid'          => $generation->uuid,
            'status'        => $generation->status,
            'completed'     => in_array($generation->status, ['completed', 'failed']),
            'progress'      => $progress,
            'output_url'    => $generation->generated_image ? asset($generation->generated_image) : null,
            'preview_url'   => $generation->preview_path    ? asset($generation->preview_path)    : null,
            'hd_url'        => $generation->hd_path         ? asset($generation->hd_path)         : null,
            'error_message' => $generation->error_message,
        ], 'OK');
    }
}
