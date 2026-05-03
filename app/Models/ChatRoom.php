<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
