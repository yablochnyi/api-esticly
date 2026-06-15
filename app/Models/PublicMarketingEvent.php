<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublicMarketingEvent extends Model
{
    protected $fillable = [
        'event',
        'locale',
        'path',
        'page_url',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'fbclid_hash',
        'gclid_hash',
        'visitor_id_hash',
        'ip_hash',
        'user_agent',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];
}
