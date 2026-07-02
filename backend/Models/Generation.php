<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Generation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'uuid',
        'title',
        'room_type',
        'flooring_type',
        'style',
        'input_data',
        'original_image',
        'generated_image',
        'credits_used',
        'status',
        'external_id',
        'full_image_url',
        'error_message',
        'processing_time',
        'metadata',
        'processed_at',
        'texture_id',
        'floor_polygon',
        'homography_matrix',
        'preview_path',
        'hd_path',
        'processing_method',
        'ai_cost',
        'source',
        'guest_ip',
        'api_key_id',
        'guest_session_id',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'metadata' => 'array',
            'floor_polygon' => 'array',
            'homography_matrix' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($generation) {
            if (empty($generation->uuid)) {
                $generation->uuid = Str::uuid();
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function texture(): BelongsTo
    {
        return $this->belongsTo(Texture::class);
    }

    // Helper Methods
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getOriginalImageUrlAttribute(): ?string
    {
        return $this->original_image ? asset($this->original_image) : null;
    }

    public function getGeneratedImageUrlAttribute(): ?string
    {
        return $this->generated_image ? asset($this->generated_image) : null;
    }

    public function getPreviewUrlAttribute(): ?string
    {
        return $this->preview_path ? asset($this->preview_path) : null;
    }

    public function getHdUrlAttribute(): ?string
    {
        return $this->hd_path ? asset($this->hd_path) : null;
    }
}
