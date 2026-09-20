<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateElement extends Model
{
    protected $fillable = [
        'template_id',
        'source_element_id',
        'name',
        'field',
        'type',
        'x',
        'y',
        'width',
        'height',
        'font_id',
        'media_file_id',
        'opacity',
        'rotation',
        'z_index',
        'visible',
        'locked',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'visible' => 'boolean',
        'locked' => 'boolean',
        'rotation' => 'float',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            VideoTemplate::class,
            'template_id'
        );
    }

    public function font(): BelongsTo
    {
        return $this->belongsTo(
            Font::class,
            'font_id'
        );
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(
            MediaFile::class,
            'media_file_id'
        );
    }
}