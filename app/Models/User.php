<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Support\Auditable;
use App\Support\AdminAccess;
use App\Support\EncryptedValue;
use App\Support\PhoneIndex;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Auditable;

    protected array $auditExclude = [
        'password',
        'remember_token',
        'phone_hash',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = false;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'phone_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'schedule' => 'array',
            'online_booking_enabled' => 'boolean',
            'online_booking_whitelist_only' => 'boolean',
            'online_booking_auto_confirm' => 'boolean',
            'online_booking_period_days' => 'integer',
            'language_code' => 'string',
            'registered_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'subscription_provider' => 'string',
        ];
    }

    public function getPhoneAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setPhoneAttribute($value): void
    {
        $normalized = PhoneIndex::normalize(is_string($value) ? $value : null);
        $this->attributes['phone'] = EncryptedValue::encryptNullable($normalized);
        $this->attributes['phone_hash'] = PhoneIndex::hash($normalized);
    }

    public function getAddressAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setAddressAttribute($value): void
    {
        $this->attributes['address'] = EncryptedValue::encryptNullable(is_string($value) ? $value : null);
    }

    public function getDescriptionAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['description'] = EncryptedValue::encryptNullable(is_string($value) ? $value : null);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscriptionTransactions()
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return AdminAccess::allows($this);
    }
}
