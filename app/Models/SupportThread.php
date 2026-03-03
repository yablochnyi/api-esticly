<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;

class SupportThread extends Model
{
    use Auditable;

    protected $guarded = false;

    public function messages()
    {
        return $this->hasMany(SupportMessage::class, 'thread_id');
    }
}

