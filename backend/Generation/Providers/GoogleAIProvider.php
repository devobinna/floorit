<?php

namespace App\Generation\Providers;

use App\Models\Generation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAIProvider implements AIProviderInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int    $timeout;

    public function __construct()
    {
        $this->apiKey  = config('services.google_ai.api_key', '');
        $this->baseUrl = config('services.google_ai.base_url', 'https://generativelanguage.googleapis.com');
        $this->model   = config('services.google_ai.model', 'gemini-2.0-flash-exp');
        $this->timeout = (int) config('services.google_ai.timeout', 300);
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function supportsPolling(): bool
    {
        return false; // Gemini is fully synchronous
    }

    public function poll(Generation $generation): array
    {
        return [
            'status'     => $generation->status === 'completed' ? 'completed' : 'processing',
            'result_url' => null,
            'error'      => null,
        ];
    }

    // ─── Generation ──────────────────────────────────────────────────────────

    /**
     * Submit a generation request to Gemini.
     *
     * When $cachedTextureUri is provided (a Google File API URI), the texture is
     * referenced by URI instead of being re-uploaded as inline base64 — saves
     * bandwidth and is faster for large texture files.
     */
    public function submit(
        Generation $generation,
        string     $roomImagePath,
        string     $textureImagePath,
        string     $prompt,
        ?string    $cachedTextureUri = null
    ): array {
        try {
            Log::info('GoogleAI: Submitting generation', [
                'generation_id'     => $generation->id,
                'model'             => $this->model,
                'texture_cached'    => $cachedTextureUri !== null,
            ]);

            if (!file_exists($roomImagePath)) {
                throw new \Exception("Room image not found: {$roomImagePath}");
            }

            // Build room image part — always inline (unique per generation)
            $roomBase64 = base64_encode(file_get_contents($roomImagePath));
            $roomMime   = $this->getMimeType($roomImagePath);

            // Build texture part — use cached File API URI if available, else inline
            if ($cachedTextureUri !== null) {
                $texturePart = [
                    'file_data' => [
                        'mime_type' => $this->getMimeFromExtension($textureImagePath),
                        'file_uri'  => $cachedTextureUri,
                    ],
                ];
                Log::info('GoogleAI: Using cached texture URI', ['uri' => $cachedTextureUri]);
            } else {
                if (!file_exists($textureImagePath)) {
                    throw new \Exception("Texture image not found: {$textureImagePath}");
                }
                $texturePart = [
                    'inline_data' => [
                        'mime_type' => $this->getMimeType($textureImagePath),
                        'data'      => base64_encode(file_get_contents($textureImagePath)),
                    ],
                ];
                Log::info('GoogleAI: Using inline texture data (no cache)');
            }

            $payload = [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $roomMime, 'data' => $roomBase64]],
                        $texturePart,
                    ],
                ]],
                'generationConfig' => [
                    'temperature'        => 0.4,
                    'topK'               => 32,
                    'topP'               => 1,
                    'maxOutputTokens'    => 4096,
                    'responseModalities' => ['IMAGE'],
                ],
            ];

            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

            if (!$response->successful()) {
                $error = $response->json('error.message', $response->body());
                throw new \Exception("Google AI API error ({$response->status()}): {$error}");
            }

            // Extract image data — Gemini returns camelCase (inlineData) or snake_case (inline_data)
            // depending on the model version. Check both to be safe.
            $imageData = null;
            $textParts = [];
            foreach ($response->json('candidates.0.content.parts', []) as $part) {
                $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;
                if (!empty($inlineData['data'])) {
                    $imageData = base64_decode($inlineData['data']);
                    break;
                }
                if (isset($part['text'])) {
                    $textParts[] = $part['text'];
                }
            }

            if (!$imageData) {
                $finishReason = $response->json('candidates.0.finishReason', 'UNKNOWN');
                Log::warning('GoogleAI: No image in response', [
                    'generation_id' => $generation->id,
                    'finish_reason' => $finishReason,
                    'model'         => $this->model,
                    'text_parts'    => array_map(fn ($t) => substr($t, 0, 300), $textParts),
                    'parts_count'   => count($response->json('candidates.0.content.parts', [])),
                    'raw_excerpt'   => substr($response->body(), 0, 1000),
                ]);
                throw new \Exception("Google AI returned no image. finishReason: {$finishReason}");
            }

            Log::info('GoogleAI: Generation complete', ['generation_id' => $generation->id]);

            return [
                'success'    => true,
                'completed'  => true,
                'image_data' => $imageData,
                'task_id'    => 'google_' . uniqid() . '_' . time(),
            ];

        } catch (\Exception $e) {
            Log::error('GoogleAI: Generation failed', [
                'generation_id' => $generation->id,
                'error'         => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ─── Google File API ─────────────────────────────────────────────────────

    /**
     * Upload a file to the Google File API using multipart upload.
     * Files are stored for up to 48 hours and referenced by URI in future requests.
     *
     * Returns ['uri' => string, 'expires_at' => Carbon, 'name' => string]
     * Throws \Exception on failure.
     */
    public function uploadFileToApi(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found for upload: {$filePath}");
        }

        $mimeType    = $this->getMimeType($filePath);
        $displayName = basename($filePath);
        $fileContent = file_get_contents($filePath);
        $boundary    = 'boundary_' . bin2hex(random_bytes(8));

        // Multipart/related body: JSON metadata part + binary file part
        $metadata = json_encode(['file' => ['display_name' => $displayName]]);
        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= $metadata . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$mimeType}\r\n\r\n";
        $body .= $fileContent . "\r\n";
        $body .= "--{$boundary}--";

        Log::info('GoogleAI: Uploading file to File API', [
            'file'      => $displayName,
            'mime_type' => $mimeType,
            'size_kb'   => round(strlen($fileContent) / 1024, 1),
        ]);

        $response = Http::timeout(120)
            ->withHeaders(['X-Goog-Upload-Protocol' => 'multipart'])
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post("{$this->baseUrl}/upload/v1beta/files?key={$this->apiKey}");

        if (!$response->successful()) {
            $error = $response->json('error.message', $response->body());
            throw new \Exception("Google File API upload error ({$response->status()}): {$error}");
        }

        $fileData = $response->json('file');

        if (empty($fileData['uri'])) {
            throw new \Exception('Google File API returned no URI in response: ' . $response->body());
        }

        // Google returns ISO 8601 expiration time; default to 47h if missing
        $expiresAt = !empty($fileData['expirationTime'])
            ? Carbon::parse($fileData['expirationTime'])
            : now()->addHours(47);

        Log::info('GoogleAI: File uploaded successfully', [
            'file'       => $displayName,
            'uri'        => $fileData['uri'],
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return [
            'uri'        => $fileData['uri'],
            'expires_at' => $expiresAt,
            'name'       => $fileData['name'] ?? null,
        ];
    }

    /**
     * Delete a file from the Google File API (optional cleanup).
     * Non-critical — files expire automatically after 48h.
     */
    public function deleteFileFromApi(string $fileName): bool
    {
        try {
            $response = Http::timeout(30)
                ->delete("{$this->baseUrl}/v1beta/{$fileName}?key={$this->apiKey}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('GoogleAI: Failed to delete file from File API', [
                'name'  => $fileName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function getMimeType(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $path);
            finfo_close($finfo);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        return $this->getMimeFromExtension($path);
    }

    private function getMimeFromExtension(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            'gif'         => 'image/gif',
            default       => 'image/jpeg',
        };
    }
}
