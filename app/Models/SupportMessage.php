<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;

class SupportMessage extends Model
{
    use Auditable;

    protected $guarded = false;
    protected array $auditExclude = ['body'];

    public function thread()
    {
        return $this->belongsTo(SupportThread::class, 'thread_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }
}
