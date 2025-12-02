<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    protected $fillable = [
        'correlation_id',
        'user_id',
        'endpoint',
        'method',
        'status_code',
        'request_payload',
        'response_body',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
    ];
}

