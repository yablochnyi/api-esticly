<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Support\Audit;
use App\Support\MediaUrl;
use App\Support\OrgSubscription;
use App\Support\PhoneIndex;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    private function normalizePhoneNullable($phone): ?string
    {
        $raw = trim((string)$phone);
        if ($raw === '') return null;

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);
        $digits = is_string($digits) ? $digits : '';
        if ($digits === '') return null;

        return $hasPlus ? ('+' . $digits) : $digits;
    }

    private function forbidStaffUser(Request $request): void
    {
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }

        $orgId = $request->user()->organization_id ?? $request->user()->id;
        $org = User::query()->findOrFail($orgId);
        if (! OrgSubscription::canManageStaff($org)) {
            throw new HttpResponseException(
                response()->json(['message' => 'subscription_pro_required'], 402)
            );
        }
    }

    public function index(Request $request)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $q = Staff::query()
            ->where('user_id', $orgId)
            ->with(['services:id'])
            ->orderByDesc('is_active')
            ->orderBy('name')
        ;

        // Optional filter: return only staff available on given org-local day.
        // We only filter by explicit time off here (vacation/day_off), not by weekly schedule.
        $date = $request->query('date');
        if ($date && Schema::hasTable('staff_time_offs')) {
            try {
                $d = Carbon::createFromFormat('Y-m-d', (string)$date)->toDateString();
            } catch (\Throwable $e) {
                $d = null;
            }
            if ($d) {
                $q->whereNotExists(function ($qq) use ($d) {
                    $qq->select(DB::raw(1))
                        ->from('staff_time_offs')
                        ->whereColumn('staff_time_offs.staff_id', 'staff.id')
                        ->where('staff_time_offs.date', $d);
                });
            }
        }

        return $q->get()->map(fn ($s) => $this->dto($s));
    }

    public function show(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);

        $staff->load(['services:id']);

        return response()->json($this->dto($staff));
    }

    public function store(Request $request)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $data = $this->validateInput($request);

        $serviceIds = $this->decodeJsonArray($request->input('service_ids', '[]'));
        $permissions = $this->decodeJsonObject($request->input('permissions', '{}'));
        $schedule = $this->decodeJsonObject($request->input('schedule', '{}'));

        if (empty($data['phone'])) {
            $permissions = [];
        }

        $normalizedPhone = $this->normalizePhoneNullable($data['phone'] ?? null);
        $this->assertPhoneUniqueOr422($normalizedPhone, null);

        $staff = Staff::query()->create([
            'user_id' => $orgId,
            'name' => $data['name'],
            'phone' => $normalizedPhone,
            'is_active' => (bool)$data['is_active'],
            'permissions' => $permissions,
            'schedule' => empty($schedule) ? null : $schedule,
        ]);

        if ($request->hasFile('photo')) {
            $staff->photo_path = $request->file('photo')->store('staff', 'public');
            $staff->save();
        }

        $beforeServiceIds = [];
        $validIds = User::findOrFail($orgId)->services()->whereIn('id', $serviceIds)->pluck('id')->all();
        $staff->services()->sync($validIds);
        $afterServiceIds = $staff->services()->pluck('services.id')->all();
        Audit::custom(
            (int) $orgId,
            'staff_services_sync',
            ['staff_id' => (int) $staff->id, 'service_ids' => $beforeServiceIds],
            ['staff_id' => (int) $staff->id, 'service_ids' => $afterServiceIds],
            ['service_ids'],
            'api_mobile',
        );

        $this->syncStaffUser($request, $staff);

        return $this->show($request, $staff);
    }

    public function update(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);

        $data = $this->validateInput($request);

        $serviceIds = $this->decodeJsonArray($request->input('service_ids', '[]'));
        $permissions = $this->decodeJsonObject($request->input('permissions', '{}'));
        $schedule = $this->decodeJsonObject($request->input('schedule', '{}'));

        if (empty($data['phone'])) {
            $permissions = [];
        }

        $normalizedPhone = $this->normalizePhoneNullable($data['phone'] ?? null);
        $this->assertPhoneUniqueOr422($normalizedPhone, (int) $staff->id);

        $staff->update([
            'name' => $data['name'],
            'phone' => $normalizedPhone,
            'is_active' => (bool)$data['is_active'],
            'permissions' => $permissions,
            'schedule' => empty($schedule) ? null : $schedule,
        ]);

        if ($request->hasFile('photo')) {
            $staff->photo_path = $request->file('photo')->store('staff', 'public');
            $staff->save();
        }

        $beforeServiceIds = $staff->services()->pluck('services.id')->all();
        $validIds = User::findOrFail($orgId)->services()->whereIn('id', $serviceIds)->pluck('id')->all();
        $staff->services()->sync($validIds);
        $afterServiceIds = $staff->services()->pluck('services.id')->all();
        Audit::custom(
            (int) $orgId,
            'staff_services_sync',
            ['staff_id' => (int) $staff->id, 'service_ids' => $beforeServiceIds],
            ['staff_id' => (int) $staff->id, 'service_ids' => $afterServiceIds],
            ['service_ids'],
            'api_mobile',
        );

        $this->syncStaffUser($request, $staff);

        return $this->show($request, $staff);
    }

    // ===== schedule endpoints =====

    public function schedule(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);

        return response()->json([
            'schedule' => $staff->schedule ?? new \stdClass(),
        ]);
    }

    public function updateSchedule(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);

        $data = $request->validate([
            'schedule' => ['required', 'string'],
        ]);

        $schedule = json_decode($data['schedule'], true);
        if (!is_array($schedule)) {
            return response()->json(['message' => 'Invalid schedule'], 422);
        }

        $staff->schedule = $schedule;
        $staff->save();

        return response()->json([
            'schedule' => $staff->schedule,
        ]);
    }

    // ===== time off endpoints =====

    public function timeOffs(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);
        abort_unless(Schema::hasTable('staff_time_offs'), 404);

        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $q = StaffTimeOff::query()
            ->where('staff_id', $staff->id)
            ->orderBy('date', 'desc');

        if (!empty($data['from']) && !empty($data['to'])) {
            $q->whereBetween('date', [$data['from'], $data['to']]);
        } elseif (!empty($data['from'])) {
            $q->where('date', '>=', $data['from']);
        } elseif (!empty($data['to'])) {
            $q->where('date', '<=', $data['to']);
        }

        return $q->get()->map(fn ($x) => [
            'id' => $x->id,
            'date' => $x->date?->format('Y-m-d'),
            'type' => $x->type,
            'note' => $x->note,
        ]);
    }

    public function upsertTimeOff(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);
        abort_unless(Schema::hasTable('staff_time_offs'), 404);

        $data = $request->validate([
            // either date OR from+to
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['required', 'string', 'in:day_off,vacation'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $dates = [];
        if (!empty($data['date'])) {
            $dates[] = $data['date'];
        } elseif (!empty($data['from']) && !empty($data['to'])) {
            $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', $data['to'])->startOfDay();
            if ($to->lt($from)) {
                [$from, $to] = [$to, $from];
            }
            for ($d = clone $from; $d->lte($to); $d->addDay()) {
                $dates[] = $d->toDateString();
            }
        } else {
            return response()->json(['message' => 'Invalid date'], 422);
        }

        DB::transaction(function () use ($dates, $staff, $data) {
            foreach ($dates as $d) {
                StaffTimeOff::query()->updateOrCreate(
                    ['staff_id' => $staff->id, 'date' => $d],
                    ['type' => $data['type'], 'note' => $data['note'] ?? null],
                );
            }
        });

        return $this->timeOffs($request, $staff);
    }

    public function deleteTimeOff(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);
        abort_unless(Schema::hasTable('staff_time_offs'), 404);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        StaffTimeOff::query()
            ->where('staff_id', $staff->id)
            ->where('date', $data['date'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    // ===== helpers =====

    private function validateInput(Request $request): array
    {
        return $request->validate([
            'name' => ['required','string','max:255'],
            'phone' => ['nullable','string','max:50'],
            'is_active' => ['required'],
            'service_ids' => ['nullable','string'],
            'permissions' => ['nullable','string'],
            'schedule' => ['nullable','string'],
            'photo' => ['nullable','image','max:5120'],
        ]);
    }

    private function decodeJsonArray($raw): array
    {
        if (is_array($raw)) return $raw;
        $arr = json_decode((string)$raw, true);
        return is_array($arr) ? $arr : [];
    }

    private function decodeJsonObject($raw): array
    {
        if (is_array($raw)) return $raw;
        $obj = json_decode((string)$raw, true);
        return is_array($obj) ? $obj : [];
    }

    private function dto(Staff $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'phone' => $s->phone,
            'is_active' => (bool)$s->is_active,
            'service_ids' => $s->services->pluck('id')->values(),
            'permissions' => $s->permissions ?? new \stdClass(),
            'schedule' => $s->schedule ?? new \stdClass(),
            'photo_url' => MediaUrl::publicFile($s->photo_path),
        ];
    }

    private function assertPhoneUniqueOr422(?string $phone, ?int $ignoreStaffId): void
    {
        if (empty($phone)) {
            return;
        }

        $phoneHash = PhoneIndex::hash($phone);
        if (!$phoneHash) {
            return;
        }

        $q = Staff::withTrashed()->where('phone_hash', $phoneHash);
        if ($ignoreStaffId) {
            $q->where('id', '!=', $ignoreStaffId);
        }

        if ($q->exists()) {
            abort(response()->json(['message' => 'phone_already_used'], 422));
        }
    }

    private function syncStaffUser(Request $request, Staff $staff): void
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $baseAccess = (bool)($staff->permissions['base_access'] ?? false);
        if (!empty($staff->phone) && $baseAccess) {

            // Prefer keeping a single user per staff member (avoid duplicates when phone format changes).
            $u = null;
            if ($staff->staff_user_id) {
                $u = User::find($staff->staff_user_id);
            }
            if (!$u) {
                $u = User::where('staff_id', $staff->id)->first();
            }
            if (!$u) {
                if (Schema::hasColumn('users', 'phone_hash')) {
                    $u = User::where('phone_hash', PhoneIndex::hash($staff->phone))->first();
                } else {
                    $u = User::where('phone', $staff->phone)->first();
                }
            }
            if (!$u) {
                $u = User::create([
                    'phone' => $staff->phone,
                    'password' => bcrypt(Str::random(32)),
                    'phone_verified_at' => now(),
                ]);
            }

            if (!$u->phone_verified_at) {
                $u->phone_verified_at = now();
            }

            // If phone was updated on staff side, sync it to user (if not already used).
            if (!empty($staff->phone) && $u->phone !== $staff->phone) {
                if (Schema::hasColumn('users', 'phone_hash')) {
                    $exists = User::where('phone_hash', PhoneIndex::hash($staff->phone))
                        ->where('id', '!=', $u->id)
                        ->exists();
                } else {
                    $exists = User::where('phone', $staff->phone)->where('id', '!=', $u->id)->exists();
                }
                if (!$exists) {
                    $u->phone = $staff->phone;
                }
            }

            $u->name = $staff->name;
            $u->organization_id = $orgId;
            $u->staff_id = $staff->id;
            $u->save();

            $u->syncRoles(['staff']);

            // Also keep reverse link (optional, but handy)
            if ($staff->staff_user_id !== $u->id) {
                $staff->staff_user_id = $u->id;
                $staff->save();
            }

            // If staff is not active, revoke existing tokens (force logout).
            if (!$staff->is_active) {
                $u->tokens()->delete();
            }
            return;
        }

        // Staff has no base access (or no phone) => keep user record linked,
        // but revoke tokens so they can't continue using the app.
        $u = User::where('staff_id', $staff->id)->first();
        if ($u) {
            $u->tokens()->delete();

            // IMPORTANT: Do NOT detach staff from users table.
            // We must keep history + allow showing proper "disabled" message on login.
            $u->organization_id = $orgId;
            $u->staff_id = $staff->id;
            $u->save();

            if ($staff->staff_user_id !== $u->id) {
                $staff->staff_user_id = $u->id;
                $staff->save();
            }
        }
    }

    public function destroy(Request $request, Staff $staff)
    {
        $this->forbidStaffUser($request);
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        abort_unless($staff->user_id === $orgId, 404);

        // Disable & revoke tokens (but keep DB history)
        $staff->is_active = false;
        $staff->save();

        $u = User::where('staff_id', $staff->id)->first();
        if ($u) {
            $u->tokens()->delete();
        }

        $staff->delete(); // soft delete (migration adds deleted_at)

        return response()->json(['ok' => true]);
    }
}
