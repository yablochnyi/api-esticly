<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleCalendarConnection extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['status' => 'disconnected', 'sync_cursor' => 0, 'locale' => 'en'];

    protected $hidden = ['access_token', 'refresh_token', 'state_hash', 'google_subject'];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'email' => 'encrypted',
        'token_expires_at' => 'datetime',
        'state_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'sync_cursor' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
