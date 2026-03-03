<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EncryptedValue
{
    public static function decryptMaybe(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Backward compatibility for old plaintext rows.
            return $value;
        }
    }

    public static function encryptNullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plain = trim($value);
        if ($plain === '') {
            return null;
        }

        return Crypt::encryptString($plain);
    }
}

