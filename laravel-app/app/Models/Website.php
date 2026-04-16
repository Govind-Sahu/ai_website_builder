<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'description',
        'title',
        'tagline',
        'about_section',
        'services',
    ];

    protected $casts = [
        'services' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function generationHistories()
    {
        return $this->hasMany(GenerationHistory::class);
    }
}
