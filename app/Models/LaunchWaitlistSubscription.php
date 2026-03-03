<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LaunchWaitlistSubscription extends Model
{
    protected $fillable = [
        'email',
        'phone',
        'locale',
        'ip',
        'user_agent',
        'subscribed_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
    ];
}

