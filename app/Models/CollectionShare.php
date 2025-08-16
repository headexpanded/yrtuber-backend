<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_id',
        'user_id',
        'platform',
        'url',
        'shared_at',
        'share_type',
        'analytics',
        'expires_at',
    ];

    protected $casts = [
        'shared_at' => 'datetime',
        'analytics' => 'array',
        'expires_at' => 'datetime',
    ];

    /**
     * @return BelongsTo
     */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for active shares
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for expired shares
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Check if share is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Update analytics
     */
    public function updateAnalytics(string $action): void
    {
        $analytics = $this->analytics ?? [];
        $analytics[$action] = ($analytics[$action] ?? 0) + 1;
        $analytics['last_' . $action] = now()->toISOString();

        $this->update(['analytics' => $analytics]);
    }

    /**
     * Get formatted platform name
     */
    public function getFormattedPlatformAttribute(): string
    {
        $platforms = [
            'twitter' => 'Twitter',
            'facebook' => 'Facebook',
            'linkedin' => 'LinkedIn',
            'email' => 'Email',
            'link' => 'Direct Link',
        ];

        return $platforms[$this->platform] ?? ucwords(str_replace('_', ' ', $this->platform));
    }

    /**
     * Check if share is active
     */
    public function getIsActiveAttribute(): bool
    {
        return !$this->isExpired();
    }
}
