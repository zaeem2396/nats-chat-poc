<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedMessage extends Model
{
    protected $fillable = [
        'subject',
        'payload',
        'error_message',
        'error_reason',
        'attempts',
        'original_queue',
        'original_connection',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'failed_at' => 'datetime',
    ];
}
