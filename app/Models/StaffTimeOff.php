<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;

class StaffTimeOff extends Model
{
    use Auditable;

    protected $fillable = [
        'staff_id',
        'date',
        'type',
        'note',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}

