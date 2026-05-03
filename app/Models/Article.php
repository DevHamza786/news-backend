<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'status',
        'published_at',
        'source_url',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function communityNotes(): HasMany
    {
        return $this->hasMany(CommunityNote::class);
    }

    public function factChecks(): HasMany
    {
        return $this->hasMany(FactCheck::class);
    }
}
