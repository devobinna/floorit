<?php

namespace App\Jobs;

use App\Generation\Providers\GoogleAIProvider;
use App\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 360; // 6 minutes — Gemini can be slow

    public function __construct(public Generation $generation) {}

    public function handle(): void
    {
        $this->generation->refresh();

        if (!in_array($this->generation->status, ['pending', 'processing'])) {
            Log::info('ProcessGeneration: Skipping — already finished', [
                'generation_id' => $this->generation->id,
                'status'        => $this->generation->status,
            ]);
            return;
        }

        $this->generation->update(['status' => 'processing']);

        try {
            $this->generation->load('texture');

            $texture     = $this->generation->texture;
            $roomPath    = public_path($this->generation->original_image);
            $texturePath = public_path($texture->file_path);
            $prompt      = $this->buildPrompt();

            $provider = app(GoogleAIProvider::class);

            if (!$provider->isConfigured()) {
                throw new \Exception('Google AI is not configured. Check GOOGLE_AI_API_KEY in .env.');
            }

            // ── Resolve texture source ────────────────────────────────────────
            // Fast path: use the cached Google File API URI (no re-upload, no inline base64)
            // Fallback: send texture as inline base64, then queue a warm-up for next time
            $cachedUri = null;

            if ($texture->hasValidGoogleCache()) {
                $cachedUri = $texture->google_file_uri;
                Log::info('ProcessGeneration: Using cached Google File URI for texture', [
                    'generation_id' => $this->generation->id,
                    'texture_id'    => $texture->id,
                    'expires_at'    => $texture->google_file_expires_at?->toIso8601String(),
                ]);
            } else {
                Log::info('ProcessGeneration: No valid cache — using inline texture data', [
                    'generation_id' => $this->generation->id,
                    'texture_id'    => $texture->id,
                ]);

                // Dispatch one warm-up job per texture (deduplicated via Cache::add so
                // concurrent generations for the same texture only queue a single upload)
                if (Cache::add("texture_warmup_queued:{$texture->id}", 1, 300)) {
                    WarmTextureCache::dispatch($texture)->onQueue('default');
                }
            }

            $result = $provider->submit($this->generation, $roomPath, $texturePath, $prompt, $cachedUri);

            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Google AI returned no result.');
            }

            if ($result['completed'] && !empty($result['image_data'])) {
                $this->saveResult($result['image_data'], $result['task_id']);
            }

        } catch (\Throwable $e) {
            $this->markFailed($e->getMessage());
            throw $e; // Let the queue mark it failed for retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessGeneration: Permanently failed', [
            'generation_id' => $this->generation->id,
            'error'         => $exception->getMessage(),
        ]);

        $this->generation->refresh();

        if ($this->generation->status !== 'completed') {
            $this->markFailed($exception->getMessage());
        }

        // Refund credits
        if ($this->generation->credits_used > 0 && $this->generation->user_id) {
            $this->generation->user?->increment('credits', $this->generation->credits_used);
            Log::info('ProcessGeneration: Credits refunded', [
                'user_id' => $this->generation->user_id,
                'amount'  => $this->generation->credits_used,
            ]);
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function buildPrompt(): string
    {
        $texture  = $this->generation->texture;
        $roomType = $this->generation->room_type ?? 'room';

        $prompt  = "You are editing a photo of a {$roomType}. ";
        $prompt .= "The second image is a texture sample of the flooring material called \"{$texture->name}\". ";
        $prompt .= "Replace ONLY the floor surface in the first image with the exact texture, pattern, colour, and style shown in the second image. ";
        $prompt .= "Apply correct perspective, lighting, and shadow to the new floor so it looks completely photorealistic and natural in the scene. ";
        $prompt .= "Keep everything else — furniture, walls, ceiling, objects, people, lighting — exactly as they are. ";
        $prompt .= "Output a high-resolution, photorealistic interior photograph.";

        if ($this->generation->style) {
            $prompt .= " Style note: {$this->generation->style}.";
        }

        return $prompt;
    }

    private function saveResult(string $imageData, string $taskId): void
    {
        $uuid = $this->generation->uuid;

        // Save full-resolution result
        $resultDir = public_path('assets/generations/results');
        if (!is_dir($resultDir)) mkdir($resultDir, 0755, true);

        $resultRelPath = "assets/generations/results/generation_{$uuid}.jpg";
        file_put_contents(public_path($resultRelPath), $imageData);

        // Create preview (resize to max 1200px wide)
        $previewDir = public_path('assets/generations/previews');
        if (!is_dir($previewDir)) mkdir($previewDir, 0755, true);

        $previewRelPath = "assets/generations/previews/preview_{$uuid}.jpg";
        $this->createPreview(public_path($resultRelPath), public_path($previewRelPath));

        $this->generation->update([
            'status'          => 'completed',
            'external_id'     => $taskId,
            'generated_image' => $resultRelPath,
            'preview_path'    => $previewRelPath,
            'hd_path'         => $resultRelPath,
            'processed_at'    => now(),
        ]);

        Log::info('ProcessGeneration: Result saved', [
            'generation_id' => $this->generation->id,
            'path'          => $resultRelPath,
        ]);
    }

    private function createPreview(string $sourcePath, string $previewPath): void
    {
        try {
            $info = @getimagesize($sourcePath);
            if (!$info) { copy($sourcePath, $previewPath); return; }

            [$w, $h, $type] = $info;
            $src = match ($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
                IMAGETYPE_PNG  => imagecreatefrompng($sourcePath),
                IMAGETYPE_WEBP => imagecreatefromwebp($sourcePath),
                default        => false,
            };

            if (!$src) { copy($sourcePath, $previewPath); return; }

            $maxW = 1200;
            if ($w > $maxW) {
                $newW = $maxW;
                $newH = (int) round($h * $maxW / $w);
            } else {
                $newW = $w;
                $newH = $h;
            }

            $dst = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagejpeg($dst, $previewPath, 85);
            imagedestroy($src);
            imagedestroy($dst);
        } catch (\Throwable) {
            copy($sourcePath, $previewPath);
        }
    }

    private function markFailed(string $reason): void
    {
        $this->generation->update([
            'status'        => 'failed',
            'error_message' => $reason,
            'processed_at'  => now(),
        ]);
    }
}
