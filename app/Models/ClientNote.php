<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;
use App\Support\EncryptedValue;

class ClientNote extends Model
{
    use Auditable;

    protected $guarded = false;

    public function getTextAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setTextAttribute($value): void
    {
        $this->attributes['text'] = EncryptedValue::encryptNullable(is_string($value) ? $value : null);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
