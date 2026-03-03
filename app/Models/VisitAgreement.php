<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;
use App\Support\EncryptedValue;
use App\Support\MediaUrl;

class VisitAgreement extends Model
{
    use Auditable;

    protected array $auditExclude = [
        'agreement_text',
        'signature_path',
    ];

    protected $fillable = [
        'visit_id',
        'service_id',
        'agreement_text',
        'signature_path',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function getAgreementTextAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setAgreementTextAttribute($value): void
    {
        $this->attributes['agreement_text'] = EncryptedValue::encryptNullable(is_string($value) ? $value : null);
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function signatureUrl(): ?string
    {
        if (!$this->signature_path) return null;
        return MediaUrl::publicFile($this->signature_path);
    }
}
