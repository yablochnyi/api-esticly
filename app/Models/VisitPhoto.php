<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;
use App\Support\MediaUrl;

class VisitPhoto extends Model
{
    use Auditable;

    protected array $auditExclude = [
        'path',
    ];

    protected $fillable = [
        'visit_id',
        'kind', // before|after
        'path',
    ];

    protected $appends = ['url'];

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function getUrlAttribute(): ?string
    {
        return MediaUrl::publicFile($this->path);
    }
}

