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

    /**
     * @return BelongsToMany
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_video')
            ->withPivot('position', 'curator_notes')
            ->withTimestamps()
            ->orderBy('collection_video.position');
    }

    /**
     * @return MorphMany
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * @return MorphMany
     */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * @return MorphMany
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    /**
     * Get the primary collection for this video
     */
    public function getPrimaryCollectionAttribute()
    {
        return $this->collections()->orderBy('collection_video.position')->first();
    }

    /**
     * Get the owner of this video
     */
    public function getOwnerAttribute()
    {
        $collection = $this->collections()->first();
        return $collection ? $collection->user : null;
    }
}
