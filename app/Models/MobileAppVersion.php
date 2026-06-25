<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class MobileAppVersion extends Model
{
    use Auditable;

    protected $guarded = false;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
