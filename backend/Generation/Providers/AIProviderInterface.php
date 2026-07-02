<?php

namespace App\Generation\Providers;

use App\Models\Generation;

interface AIProviderInterface
{
    public function isConfigured(): bool;

    /**
     * Submit a generation to the AI provider.
     *
     * Synchronous providers (Google AI) complete the full API call here and return image bytes.
     * Async providers submit the job and return a task_id for later polling via poll().
     *
     * $cachedTextureUri — optional Google File API URI for the texture.
     *   When provided, the provider should use a fileData reference instead of re-uploading
     *   the texture as inline base64, saving bandwidth and time.
     *
     * Returns:
     *   ['success' => true,  'completed' => true,  'image_data' => bytes, 'task_id' => string]
     *   ['success' => true,  'completed' => false, 'task_id' => string]   // async providers
     *   ['success' => false, 'error' => string]
     */
    public function submit(
        Generation $generation,
        string     $roomImagePath,
        string     $textureImagePath,
        string     $prompt,
        ?string    $cachedTextureUri = null
    ): array;

    /**
     * Poll for status (async providers only).
     * Returns: ['status' => pending|processing|completed|failed, 'result_url' => ?string, 'error' => ?string]
     */
    public function poll(Generation $generation): array;

    public function supportsPolling(): bool;
}
