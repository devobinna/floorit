<?php

namespace App\Http\Controllers\Api;

use App\Generation\Exceptions\GenerationException;
use App\Generation\Exceptions\InsufficientCreditsException;
use App\Generation\GenerationContext;
use App\Generation\GenerationOrchestrator;
use App\Http\Controllers\Controller;
use App\Http\Resources\GenerationResource;
use App\Models\Generation;
use App\Models\Texture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * REST API generation endpoints.
 * Authenticated via X-API-Key header (AuthenticateApiKey middleware).
 */
class GenerationController extends Controller
{
    public function __construct(private GenerationOrchestrator $orchestrator) {}

    /**
     * POST /api/v1/generations
     */
    public function store(Request $request)
    {
        $request->validate([
            'texture_id' => 'required|integer|exists:textures,id',
            'image'      => 'required|file|mimes:jpg,jpeg,png,webp|max:20480',
            'room_type'  => 'nullable|string|max:100',
            'style'      => 'nullable|string|max:500',
        ]);

        $apiKeyData = $request->get('api_key');
        $user       = $request->get('api_user');

        $context             = new GenerationContext('api');
        $context->user       = $user;
        $context->apiKey     = \App\Models\ApiKey::find($apiKeyData['id']);
        $context->imageFile  = $request->file('image');
        $context->textureId  = (int) $request->texture_id;
        $context->roomType   = $request->room_type;
        $context->style      = $request->style;

        try {
            $generation = $this->orchestrator->handle($context);

            return (new GenerationResource($generation->load('texture')))
                ->response()
                ->setStatusCode(202);

        } catch (InsufficientCreditsException $e) {
            return apiResponse([], $e->getMessage(), false, 402);
        } catch (GenerationException $e) {
            return apiResponse([], $e->getMessage(), false, 422);
        } catch (\Throwable $e) {
            Log::error('API generation failed', ['error' => $e->getMessage()]);
            return apiResponse([], 'Internal server error.', false, 500);
        }
    }

    /**
     * GET /api/v1/generations
     */
    public function index(Request $request)
    {
        $user        = $request->get('api_user');
        $generations = Generation::where('user_id', $user->id)
            ->with('texture')
            ->latest()
            ->paginate(20);

        return GenerationResource::collection($generations);
    }

    /**
     * GET /api/v1/generations/{uuid}
     */
    public function show(Request $request, string $uuid)
    {
        $user       = $request->get('api_user');
        $generation = Generation::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->with('texture')
            ->first();

        if (!$generation) {
            return apiResponse([], 'Generation not found.', false, 404);
        }

        return new GenerationResource($generation);
    }

    /**
     * GET /api/v1/generations/{uuid}/status
     */
    public function status(Request $request, string $uuid)
    {
        $user       = $request->get('api_user');
        $generation = Generation::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->first();

        if (!$generation) {
            return apiResponse([], 'Generation not found.', false, 404);
        }

        return apiResponse([
            'uuid'              => $generation->uuid,
            'status'            => $generation->status,
            'completed'         => in_array($generation->status, ['completed', 'failed']),
            'output_url'        => $generation->generated_image ? asset($generation->generated_image) : null,
            'preview_url'       => $generation->preview_path    ? asset($generation->preview_path)    : null,
            'error_message'     => $generation->error_message,
        ], 'OK');
    }

    /**
     * GET /api/v1/textures
     */
    public function textures()
    {
        $textures = Texture::active()->ordered()->get()->map(fn ($t) => [
            'id'            => $t->id,
            'name'          => $t->name,
            'slug'          => $t->slug,
            'category'      => $t->category,
            'thumbnail_url' => $t->thumbnail_path ? asset($t->thumbnail_path) : null,
        ]);

        return apiResponse($textures->values()->all(), 'Textures retrieved.');
    }

    /**
     * GET /api/v1/credits/balance
     */
    public function creditsBalance(Request $request)
    {
        $user = $request->get('api_user');
        return apiResponse(['credits' => $user->credits ?? 0], 'Balance retrieved.');
    }
}
