<?php

namespace App\Policies;

use App\Models\DsarOperation;
use App\Models\User;
use App\Support\AdminAccess;

class DsarOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user);
    }

    public function view(User $user, DsarOperation $dsarOperation): bool
    {
        return AdminAccess::allows($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, DsarOperation $dsarOperation): bool
    {
        return false;
    }

    public function delete(User $user, DsarOperation $dsarOperation): bool
    {
        return false;
    }
}

