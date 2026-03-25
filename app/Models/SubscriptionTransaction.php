<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionTransaction extends Model
{
    protected $guarded = false;

    protected $casts = [
        'period_start_at' => 'datetime',
        'period_end_at' => 'datetime',
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
