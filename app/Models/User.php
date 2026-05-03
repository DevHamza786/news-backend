<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function getIsAdminAttribute($value): bool
    {
        $configuredAdminEmail = env('ADMIN_EMAIL');

        return (bool) $value || ($configuredAdminEmail && $this->email === $configuredAdminEmail);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_user')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function communityNotes(): HasMany
    {
        return $this->hasMany(CommunityNote::class);
    }

    public function factChecks(): HasMany
    {
        return $this->hasMany(FactCheck::class);
    }

    public function reputation(): HasOne
    {
        return $this->hasOne(UserReputation::class);
    }
}
