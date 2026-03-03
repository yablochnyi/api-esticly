<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Auditable;
use App\Support\EncryptedValue;
use App\Support\PhoneIndex;

class Staff extends Model
{
    use SoftDeletes, Auditable;

    protected array $auditExclude = [
        'phone_hash',
    ];

    protected $table = 'staff';

    protected $guarded = false;

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
        'schedule' => 'array',
    ];

    protected $hidden = [
        'phone_hash',
    ];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function timeOffs()
    {
        return $this->hasMany(StaffTimeOff::class);
    }
}
