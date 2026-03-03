<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;
use App\Support\MediaUrl;

class PortfolioPhoto extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'staff_id',
        'path',
        'caption',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return MediaUrl::publicFile($this->path);
    }
}
