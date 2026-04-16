<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function websites()
    {
        return $this->hasMany(Website::class);
    }

    public function generationHistories()
    {
        return $this->hasMany(GenerationHistory::class);
    }

    public function dailyGenerationCount(): int
    {
        return $this->generationHistories()
            ->whereDate('created_at', today())
            ->where('from_cache', false)
            ->count();
    }
}
