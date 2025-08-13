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
    ];

    protected $casts = [
        'shared_at' => 'datetime',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
