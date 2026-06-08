<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPromoCode extends Model
{
    use Auditable;

    protected $guarded = false;

    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(SubscriptionPromoCodeRedemption::class);
    }
}
