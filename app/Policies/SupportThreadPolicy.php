<?php

namespace App\Policies;

use App\Models\SupportThread;
use App\Models\User;
use App\Support\AdminAccess;

class SupportThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user);
    }

    public function view(User $user, SupportThread $supportThread): bool
    {
        return AdminAccess::allows($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SupportThread $supportThread): bool
    {
        return AdminAccess::allows($user);
    }

    public function delete(User $user, SupportThread $supportThread): bool
    {
        return false;
    }
}

