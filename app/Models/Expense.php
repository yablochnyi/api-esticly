<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'title',
        'amount',
        'payment_method',
        'expense_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
