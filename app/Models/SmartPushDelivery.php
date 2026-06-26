<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class SmartPushDelivery extends Model
{
    use Auditable;

    protected $fillable = [
        'org_id',
        'user_id',
        'type',
        'local_date',
        'title',
        'body',
        'data',
        'status',
        'sent_at',
        'error',
    ];

    protected $casts = [
        'local_date' => 'date',
        'data' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
