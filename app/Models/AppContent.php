<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppContent extends Model
{
    protected $fillable = [
        'section',
        'key',
        'title_fr',
        'title_en',
        'body_fr',
        'body_en',
        'image_path',
        'metadata',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/'.$this->image_path) : null;
    }
}