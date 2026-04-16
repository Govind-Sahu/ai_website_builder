<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GenerationHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'website_id',
        'prompt',
        'response',
        'cache_key',
        'from_cache',
    ];

    protected $casts = [
        'prompt' => 'array',
        'response' => 'array',
        'from_cache' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
