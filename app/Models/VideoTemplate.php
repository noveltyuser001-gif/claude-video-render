<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoTemplate extends Model
{
    protected $fillable = [
        'source',
        'source_template_id',
        'version',
        'name',
        'category',
        'video_path',
        'thumbnail',
        'width',
        'height',
        'fps',
        'bitrate',
        'duration',
        'output_format',
        'video_codec',
        'audio_codec',
        'active',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'active' => 'boolean',
        'duration' => 'float',
    ];

    public function elements(): HasMany
    {
        return $this->hasMany(
            TemplateElement::class,
            'template_id'
        );
    }
}