<?php

namespace App\Support;

use App\Models\Staff;
use Illuminate\Http\Request;

class StaffGuard
{
    /**
     * Returns current staff model for a staff-user (users.staff_id).
     * Throws 403 if staff is missing/disabled.
     */
    public static function currentOrAbort(Request $request): Staff
    {
        $u = $request->user();
        if (!$u || !$u->staff_id) {
            abort(403, 'access_denied');
        }

        /** @var Staff|null $staff */
        $staff = Staff::withTrashed()->find($u->staff_id);
        if (!$staff || $staff->deleted_at || !$staff->is_active) {
            abort(403, 'user_disabled');
        }

        $baseAccess = (bool)($staff->permissions['base_access'] ?? false);
        if (!$baseAccess) {
            abort(403, 'access_denied');
        }

        return $staff;
    }

    public static function requirePermission(Staff $staff, string $key): void
    {
        if ((bool)($staff->permissions[$key] ?? false) !== true) {
            abort(403, 'access_denied');
        }
    }
}


