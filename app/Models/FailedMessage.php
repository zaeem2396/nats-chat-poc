<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedMessage extends Model
{
    protected $fillable = [
        'subject',
        'payload',
        'error_reason',
        'original_queue',
        'original_connection',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'failed_at' => 'datetime',
    ];
}
