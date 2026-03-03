<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DsarOperation extends Model
{
    protected $guarded = false;

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function org()
    {
        return $this->belongsTo(User::class, 'org_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id')->withTrashed();
    }
}

