<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;

class DeviceToken extends Model
{
    use Auditable;

    protected array $auditExclude = [
        'token', // treat device token as sensitive; keep changes minimal
    ];

    protected $fillable = [
        'user_id',
        'platform',
        'token',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}

