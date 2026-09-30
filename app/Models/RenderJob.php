<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenderJob extends Model
{
    protected $fillable = [
        'template_id',
        'doctor_name',
        'hospital_name',
        'photo_path',
        'data',
        'output_video',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}