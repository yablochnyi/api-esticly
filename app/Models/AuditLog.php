<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $guarded = false;

    protected $casts = [
        'changed_keys' => 'array',
        'created_at' => 'datetime',

        // Encrypt payloads at rest (APP_KEY required)
        'old_values' => 'encrypted:array',
        'new_values' => 'encrypted:array',
    ];
}

