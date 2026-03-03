<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;
use App\Support\EncryptedValue;

class Service extends Model
{
    use Auditable;

    protected $guarded = false;

    public function getAgreementTextAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setAgreementTextAttribute($value): void
    {
        $this->attributes['agreement_text'] = EncryptedValue::encryptNullable(is_string($value) ? $value : null);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function staff()
    {
        return $this->belongsToMany(Staff::class, 'service_staff');
    }
}
