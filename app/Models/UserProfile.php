<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'username',
        'bio',
        'avatar',
        'website',
        'location',
        'social_links',
        'is_verified',
        'is_featured_curator',
        'follower_count',
        'following_count',
        'collection_count',
    ];

    protected $casts = [
        'social_links' => 'array',
        'is_verified' => 'boolean',
        'is_featured_curator' => 'boolean',
        'follower_count' => 'integer',
        'following_count' => 'integer',
        'collection_count' => 'integer',
    ];

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
