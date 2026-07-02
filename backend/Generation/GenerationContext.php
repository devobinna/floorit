<?php

namespace App\Generation;

use App\Models\ApiKey;
use App\Models\Generation;
use App\Models\Texture;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class GenerationContext
{
    // Caller identity
    public string $callerType; // guest | user | api | sdk
    public ?User $user = null;
    public ?ApiKey $apiKey = null;
    public ?string $guestIp = null;
    public ?string $guestSessionId = null;

    // Raw input
    public UploadedFile $imageFile;
    public int $textureId;
    public ?string $roomType = null;
    public ?string $style = null;
    public ?string $title = null;

    // Resolved during orchestration
    public ?Texture $texture = null;
    public ?string $savedImagePath = null; // relative to public/
    public ?Generation $generation = null;

    public function __construct(string $callerType)
    {
        $this->callerType = $callerType;
    }

    public function isGuest(): bool { return $this->callerType === 'guest'; }
    public function isUser(): bool  { return $this->callerType === 'user'; }
    public function isApi(): bool   { return $this->callerType === 'api'; }
    public function isSdk(): bool   { return $this->callerType === 'sdk'; }

    public function requiresCredits(): bool { return !$this->isGuest(); }

    public function creditsNeeded(): int { return 1; }

    public function source(): string { return $this->callerType; }
}
