<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;

class VisitReminderDelivery extends Model
{
    use Auditable;

    protected array $auditExclude = [
        'error',
    ];

    protected $fillable = [
        'visit_id',
        'user_id',
        'offset_min',
        'due_at',
        'sent_at',
        'status',
        'error',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function visit()
    {
        return $this->belongsTo(\App\Models\Visit::class, 'visit_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
