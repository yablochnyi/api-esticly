<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'amount_micros' => 'integer',
        'paid_at' => 'immutable_datetime',
        'period_ends_at' => 'immutable_datetime',
        'checked_at' => 'immutable_datetime',
    ];
}
