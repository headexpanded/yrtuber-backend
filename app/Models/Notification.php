<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'type',
        'data',
        'read_at',
        'actor_id',
        'subject_type',
        'subject_id',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'actor_id' => 'integer',
        'subject_id' => 'integer',
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
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @return MorphTo
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
