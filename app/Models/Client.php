<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Support\Auditable;
use App\Support\EncryptedValue;
use App\Support\PhoneIndex;

class Client extends Model
{
    use SoftDeletes, Auditable;

    protected array $auditExclude = [
        'phone_hash',
    ];

    protected $guarded = false;

    protected $casts = [
        'anonymized_at' => 'datetime',
        'deleted_at' => 'datetime',
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

    public function notes()
    {
        return $this->hasMany(\App\Models\ClientNote::class);
    }
}
