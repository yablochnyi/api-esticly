<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Support\MediaUrl;
use App\Support\OrgSubscription;
use App\Support\StaffGuard;
use App\Support\TimezoneAliases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'product_onboarding_completed' => (bool)($u->staff_id || $org->product_onboarding_completed_at),
            'subscription_provider' => $org->subscription_provider,
            ...$subscription,
        ]);
    }

    public function completeProductOnboarding(Request $request)
    {
        $this->forbidStaffUser($request);

        $u = $request->user();
        $org = $u->organization_id ? User::findOrFail($u->organization_id) : $u;

        if (!$org->product_onboarding_completed_at) {
            $org->product_onboarding_completed_at = now();
            $org->save();
        }

        return response()->json([
            'ok' => true,
            'product_onboarding_completed' => true,
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
            'company_name' => ['required','string','max:1000'],
            'description' => ['nullable','string','max:2000'],
            'address' => ['nullable','string','max:5000'],
            'email' => ['nullable','email','max:255'],
            'timezone' => ['nullable','string','max:64'],
            'language_code' => ['nullable','string','max:8'],
            'logo' => ['nullable','image','max:5120'],
        ]);

        // "logo" is an uploaded file field, not a DB column.
        unset($data['logo']);

        if (array_key_exists('timezone', $data)) {
            $data['timezone'] = TimezoneAliases::normalize($data['timezone']);
        }

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

    public function updateLanguage(Request $request)
    {
        $this->forbidStaffUser($request);
        $data = $request->validate([
            'language_code' => ['required', 'string', 'max:8'],
        ]);

        $u = $request->user();
        $org = $u->organization_id ? \App\Models\User::findOrFail($u->organization_id) : $u;

        $org->language_code = strtolower(trim($data['language_code']));
        $org->save();

        return response()->json([
            'id' => $org->id,
            'language_code' => $org->language_code,
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

    public function deleteAccount(Request $request)
    {
        $this->forbidStaffUser($request);

        $user = $request->user();
        $org = $user->organization_id ? User::query()->findOrFail($user->organization_id) : $user;
        $orgId = (int) $org->id;

        DB::transaction(function () use ($orgId): void {
            $staffIds = DB::table('staff')
                ->where('user_id', $orgId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $staffUserIds = User::query()
                ->where('organization_id', $orgId)
                ->orWhereIn('staff_id', $staffIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id !== $orgId)
                ->values()
                ->all();

            $allUserIds = array_values(array_unique(array_merge([$orgId], $staffUserIds)));

            DB::table('device_tokens')->whereIn('user_id', $allUserIds)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $allUserIds)
                ->delete();
            DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->whereIn('model_id', $allUserIds)
                ->delete();
            DB::table('sessions')->whereIn('user_id', $allUserIds)->delete();

            DB::table('support_messages')->where('org_id', $orgId)->delete();
            DB::table('support_messages')->whereIn('sender_user_id', $allUserIds)->delete();
            DB::table('support_threads')->where('org_id', $orgId)->delete();

            if ($staffUserIds !== []) {
                User::query()->whereIn('id', $staffUserIds)->delete();
            }

            User::query()->whereKey($orgId)->delete();
        });

        return response()->json(['ok' => true]);
    }
}
