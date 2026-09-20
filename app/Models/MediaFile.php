<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaFile extends Model
{
    protected $fillable = [
        'type',
        'name',
        'original_url',
        'local_path',
        'checksum',
    ];
}