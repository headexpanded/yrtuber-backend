<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'youtube_id',
        'title',
        'description',
        'thumbnail_url',
        'channel_name',
        'channel_id',
        'duration',
        'published_at',
        'view_count',
        'like_count',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
        'view_count' => 'integer',
        'like_count' => 'integer',
        'duration' => 'integer',
    ];

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_video')
            ->withPivot('position', 'curator_notes')
            ->withTimestamps()
            ->orderBy('pivot_position');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }
}
