<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'action',
        'properties',
        'ip_address',
        'user_agent',
        'target_user_id',
        'visibility',
        'aggregated_count',
    ];

    protected $casts = [
        'properties' => 'array',
        'target_user_id' => 'integer',
        'aggregated_count' => 'integer',
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Scope for public activities
     */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    /**
     * Scope for user's own activities
     */
    public function scopeOwn($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
