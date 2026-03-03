<?php

namespace App\Support;

use App\Models\User;

class AdminAccess
{
    public static function allows(?User $user): bool
    {
        return (bool) $user?->hasRole('superadmin');
    }
}

