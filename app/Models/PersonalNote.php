<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonalNote extends Model
{
    use Auditable, SoftDeletes;

    protected array $auditExclude = [
        'reminder_error',
    ];

    protected $fillable = [
        'user_id',
        'text',
        'remind_at',
        'reminder_queued_at',
        'reminder_sent_at',
        'reminder_error',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'reminder_queued_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
