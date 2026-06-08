<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPromoCodeRedemption extends Model
{
    use Auditable;

    protected $guarded = false;

    protected function casts(): array
    {
        return [
            'access_until' => 'datetime',
        ];
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPromoCode::class, 'subscription_promo_code_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(User::class, 'org_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
