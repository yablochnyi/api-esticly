<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;

class MarketingAutomation extends Model
{
    use Auditable;

    protected $guarded = false;
}

