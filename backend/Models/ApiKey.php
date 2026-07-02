<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'secret',
        'is_active',
        'abilities',
        'rate_limit',
        'total_requests',
        'last_used_at',
        'last_used_ip',
        'expires_at',
    ];

    protected $hidden = [
        'secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($apiKey) {
            if (empty($apiKey->key)) {
                $apiKey->key = 'epoxy_' . Str::random(32);
            }
            if (empty($apiKey->secret)) {
                $apiKey->secret = hash('sha256', Str::random(40));
            }
        });
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    // Alias for consistency with usage in controllers
    public function apiLogs(): HasMany
    {
        return $this->logs();
    }

    // Helper Methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function incrementUsage(): void
    {
        $this->increment('total_requests');
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => request()->ip(),
        ]);
    }
}
