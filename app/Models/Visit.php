<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Auditable;
use App\Support\EncryptedValue;
use App\Support\MediaUrl;
use App\Support\PhoneIndex;

class Visit extends Model
{
    use Auditable;

    protected array $auditExclude = [
        'photo_before_path',
        'photo_after_path',
        'photo_before_urls',
        'photo_after_urls',
        'client_phone_hash',
    ];

    protected $appends = [
        'photo_before_url',
        'photo_after_url',
        'photo_before_urls',
        'photo_after_urls',
    ];

    protected $fillable = [
        'user_id',
        'service_id',
        'promo_code_id',
        'promo_code',
        'staff_id',
        'client_id',
        'client_name',
        'client_phone',
        'starts_at',
        'ends_at',
        'duration_min',
        'price',
        'payment_method',
        'promo_discount',
        'status',
        'comment',
        'photo_before_path',
        'photo_after_path',
        'photo_before_urls',
        'photo_after_urls',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'duration_min' => 'integer',
        'price' => 'decimal:2',
        'payment_method' => 'string',
        'promo_discount' => 'decimal:2',
    ];

    protected $hidden = [
        'client_phone_hash',
    ];

    public function getClientPhoneAttribute($value): ?string
    {
        return EncryptedValue::decryptMaybe($value);
    }

    public function setClientPhoneAttribute($value): void
    {
        $normalized = PhoneIndex::normalize(is_string($value) ? $value : null);
        $this->attributes['client_phone'] = EncryptedValue::encryptNullable($normalized);
        $this->attributes['client_phone_hash'] = PhoneIndex::hash($normalized);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function staff()
    {
        // Keep staff accessible even after soft-delete (history must remain).
        return $this->belongsTo(Staff::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function photos()
    {
        return $this->hasMany(VisitPhoto::class);
    }

    public function getPhotoBeforeUrlAttribute(): ?string
    {
        $first = $this->getPhotoBeforeUrlsAttribute()[0] ?? null;
        if ($first) return $first;
        return MediaUrl::publicFile($this->photo_before_path);
    }

    public function getPhotoAfterUrlAttribute(): ?string
    {
        $first = $this->getPhotoAfterUrlsAttribute()[0] ?? null;
        if ($first) return $first;
        return MediaUrl::publicFile($this->photo_after_path);
    }

    public function getPhotoBeforeUrlsAttribute(): array
    {
        $q = $this->relationLoaded('photos') ? $this->photos->where('kind', 'before') : $this->photos()->where('kind', 'before')->get();
        return $q->values()->map(fn($p) => MediaUrl::publicFile($p->path))->filter()->values()->toArray();
    }

    public function getPhotoAfterUrlsAttribute(): array
    {
        $q = $this->relationLoaded('photos') ? $this->photos->where('kind', 'after') : $this->photos()->where('kind', 'after')->get();
        return $q->values()->map(fn($p) => MediaUrl::publicFile($p->path))->filter()->values()->toArray();
    }
}
