<?php

namespace App\Generation;

use App\Generation\Exceptions\GenerationException;
use App\Generation\Exceptions\InsufficientCreditsException;
use App\Generation\Exceptions\RateLimitExceededException;
use App\Jobs\ProcessGeneration;
use App\Models\Generation;
use App\Models\Texture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerationOrchestrator
{
    private const GUEST_DAILY_LIMIT = 2;

    /**
     * Main entry point for all four caller types.
     * Validates, reserves credits, saves image, creates record, dispatches job.
     */
    public function handle(GenerationContext $context): Generation
    {
        $this->resolveTexture($context);
        $this->checkRateLimit($context);

        if ($context->requiresCredits()) {
            $this->reserveCredits($context);
        }

        try {
            $context->savedImagePath = $this->saveImage($context->imageFile, Str::uuid());
            $generation = $this->createRecord($context);
            ProcessGeneration::dispatch($generation)->onQueue('default');

            Log::info('GenerationOrchestrator: Job dispatched', [
                'generation_id' => $generation->id,
                'caller_type'   => $context->callerType,
            ]);

            return $generation;
        } catch (\Throwable $e) {
            // If image was saved but record creation or dispatch failed, refund credits
            if ($context->requiresCredits() && $context->user) {
                $context->user->increment('credits', $context->creditsNeeded());
                Log::info('GenerationOrchestrator: Credits refunded after early failure', [
                    'user_id' => $context->user->id,
                    'credits' => $context->creditsNeeded(),
                ]);
            }
            throw $e;
        }
    }

    // ─── Private steps ────────────────────────────────────────────────────────

    private function resolveTexture(GenerationContext $context): void
    {
        $texture = Texture::where('id', $context->textureId)
            ->where('is_active', true)
            ->first();

        if (!$texture) {
            throw new GenerationException('The selected texture is not available.');
        }

        if (!$texture->file_path || !file_exists(public_path($texture->file_path))) {
            throw new GenerationException('The texture file is missing. Please choose a different texture.');
        }

        $context->texture = $texture;
    }

    private function checkRateLimit(GenerationContext $context): void
    {
        if (!$context->isGuest()) {
            return;
        }

        $ip  = $context->guestIp;
        $key = 'guest_gen_limit:' . $ip . ':' . now()->format('Y-m-d');

        $count = (int) Cache::get($key, 0);

        if ($count >= self::GUEST_DAILY_LIMIT) {
            throw new RateLimitExceededException(
                'You have used your ' . self::GUEST_DAILY_LIMIT . ' free generations for today. Create an account for unlimited access.'
            );
        }

        // Increment BEFORE dispatching so concurrent hits are blocked
        Cache::put($key, $count + 1, now()->endOfDay());
    }

    private function reserveCredits(GenerationContext $context): void
    {
        $needed = $context->creditsNeeded();

        DB::transaction(function () use ($context, $needed) {
            $user = User::lockForUpdate()->findOrFail($context->user->id);

            if (($user->credits ?? 0) < $needed) {
                throw new InsufficientCreditsException($needed, $user->credits ?? 0);
            }

            $user->decrement('credits', $needed);
            $context->user->credits = $user->credits; // decrement() already updated the in-memory value
        });

        Log::info('GenerationOrchestrator: Credits reserved', [
            'user_id' => $context->user->id,
            'amount'  => $needed,
        ]);
    }

    private function saveImage(UploadedFile $file, string $uuid): string
    {
        $ext      = strtolower($file->getClientOriginalExtension()) ?: 'jpg';
        $filename = $uuid . '.' . $ext;
        $dir      = 'assets/generations/originals';
        $absDir   = public_path($dir);

        if (!is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $file->move($absDir, $filename);

        return $dir . '/' . $filename;
    }

    private function createRecord(GenerationContext $context): Generation
    {
        return Generation::create([
            'uuid'              => (string) Str::uuid(),
            'user_id'           => $context->user?->id,
            'texture_id'        => $context->texture->id,
            'title'             => $context->title,
            'room_type'         => $context->roomType ?? 'room',
            'style'             => $context->style,
            'original_image'    => $context->savedImagePath,
            'status'            => 'pending',
            'processing_method' => 'google_ai',
            'source'            => $context->source(),
            'credits_used'      => $context->requiresCredits() ? $context->creditsNeeded() : 0,
            'guest_ip'          => $context->guestIp,
            'guest_session_id'  => $context->guestSessionId,
            'api_key_id'        => $context->apiKey?->id,
            'input_data'        => [
                'caller_type'  => $context->callerType,
                'texture_id'   => $context->textureId,
                'texture_name' => $context->texture?->name,
                'room_type'    => $context->roomType,
                'style'        => $context->style,
            ],
        ]);
    }
}
