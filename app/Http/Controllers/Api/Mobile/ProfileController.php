<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Support\MediaUrl;
use App\Support\OrgSubscription;
use App\Support\StaffGuard;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private function forbidStaffUser(Request $request): void
    {
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }
    }

    public function me(Request $request)
    {
        $u = $request->user();

        // если это staff-user, профиль организации нужен для timezone/schedule
        $org = $u->organization_id ? \App\Models\User::find($u->organization_id) : $u;
        $subscription = OrgSubscription::status($org);

        return response()->json([
            'id' => $org->id,
            'phone' => $org->phone,
            'email' => $org->email,
            'company_name' => $org->company_name,
            'address' => $org->address,
            'description' => $org->description,
            'currency_code' => $org->currency_code,
            'timezone' => $org->timezone,
            'language_code' => $org->language_code,
            'logo_url' => MediaUrl::publicFile($org->logo_path),
            'is_staff' => (bool)$u->staff_id,
            'staff_id' => $u->staff_id,
            ...$subscription,
        ]);
    }

    public function staffMe(Request $request)
    {
        $u = $request->user();
        if (!$u->staff_id) {
            return response()->json(['message' => 'access_denied'], 403);
        }

        $staff = StaffGuard::currentOrAbort($request);
        $staff->load(['services:id']);

        return response()->json([
            'id' => $staff->id,
            'name' => $staff->name,
            'phone' => $staff->phone,
            'is_active' => (bool)$staff->is_active,
            'service_ids' => $staff->services->pluck('id')->values(),
            'permissions' => $staff->permissions ?? new \stdClass(),
            'schedule' => $staff->schedule ?? new \stdClass(),
            'photo_url' => MediaUrl::publicFile($staff->photo_path),
        ]);
    }

    public function update(Request $request)
    {
        $this->forbidStaffUser($request);
        $u = $request->user();
        $org = $u->organization_id ? \App\Models\User::findOrFail($u->organization_id) : $u;

        $data = $request->validate([
            'company_name' => ['required','string','max:255'],
            'description' => ['nullable','string','max:2000'],
            'address' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'timezone' => ['nullable','string','max:64'],
            'language_code' => ['nullable','string','max:8'],
            'logo' => ['nullable','image','max:5120'],
        ]);

        // "logo" is an uploaded file field, not a DB column.
        unset($data['logo']);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            if (!is_string($path) || $path === '') {
                return response()->json(['message' => 'file_store_failed'], 500);
            }
            $data['logo_path'] = $path;
        }

        $org->fill($data);
        $org->save();

        return response()->json([
            'user' => [
                'id' => $org->id,
                'phone' => $org->phone,
                'email' => $org->email,
                'company_name' => $org->company_name,
                'address' => $org->address,
                'description' => $org->description,
                'timezone' => $org->timezone,
                'language_code' => $org->language_code,
                'logo_path' => $org->logo_path,
            ],
            'logo_url' => MediaUrl::publicFile($org->logo_path),
        ]);
    }

    public function updateCurrency(Request $request)
    {
        $this->forbidStaffUser($request);
        $data = $request->validate([
            'currency_code' => ['required','string','max:10'],
        ]);

        $u = $request->user();
        $org = $u->organization_id ? \App\Models\User::findOrFail($u->organization_id) : $u;

        $org->currency_code = $data['currency_code'];
        $org->save();

        return response()->json([
            'id' => $org->id,
            'currency_code' => $org->currency_code,
        ]);
    }

    public function schedule(Request $request)
    {
        $u = $request->user();
        $org = $u->organization_id ? \App\Models\User::findOrFail($u->organization_id) : $u;

        return response()->json([
            'schedule' => $org->schedule ?? new \stdClass(),
        ]);
    }

    public function updateSchedule(Request $request)
    {
        $this->forbidStaffUser($request);
        $data = $request->validate([
            'schedule' => ['required','string'],
        ]);

        $schedule = json_decode($data['schedule'], true);
        if (!is_array($schedule)) {
            return response()->json(['message' => 'Invalid schedule'], 422);
        }

        $u = $request->user();
        $org = $u->organization_id ? \App\Models\User::findOrFail($u->organization_id) : $u;

        $org->schedule = $schedule;
        $org->save();

        return response()->json([
            'schedule' => $org->schedule,
        ]);
    }

    public function reminders(Request $request)
    {
        $u = $request->user();
        $raw = $u->reminder_offsets_min;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) $raw = [];
        $out = [];
        foreach ($raw as $x) {
            if (is_numeric($x)) {
                $v = (int)$x;
                if ($v > 0 && $v <= 60 * 24 * 7) $out[] = $v;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return response()->json([
            'offsets_min' => $out,
        ]);
    }

    public function updateReminders(Request $request)
    {
        $data = $request->validate([
            'offsets_min' => ['required', 'array', 'min:0', 'max:20'],
            'offsets_min.*' => ['integer', 'min:1', 'max:' . (60 * 24 * 7)],
        ]);

        $u = $request->user();
        $arr = array_values(array_unique(array_map('intval', $data['offsets_min'])));
        sort($arr);
        $u->reminder_offsets_min = $arr;
        $u->save();

        return $this->reminders($request);
    }
}
