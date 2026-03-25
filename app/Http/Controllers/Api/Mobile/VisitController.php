<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Jobs\SendMarketingAutomationSms;
use App\Models\Client;
use App\Models\MarketingAutomation;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffTimeOff;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitAgreement;
use App\Support\PromoCodes;
use App\Support\OrgSubscription;
use App\Support\StaffGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    // Return counts per org-local day for day strip (fast).
    // Query params: from=Y-m-d, to=Y-m-d (in organization timezone)
    public function counts(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $org = User::query()->findOrFail($orgId);
        if (!OrgSubscription::canCreateVisits($org)) {
            return response()->json(['message' => 'subscription_required'], 402);
        }
        $tz = $org->timezone ?: 'Europe/Warsaw';

        $fromLocal = Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay();
        $toLocal = Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->endOfDay();

        $fromUtc = (clone $fromLocal)->utc();
        $toUtc = (clone $toLocal)->utc();

        $q = Visit::query()
            ->where('user_id', $orgId)
            ->whereBetween('starts_at', [$fromUtc, $toUtc])
            ->select(['starts_at']);

        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            $q->where('staff_id', (int)$staff->id);
        }

        $counts = [];
        // init with 0 for all days in range
        $cur = Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->startOfDay();
        while ($cur->lte($end)) {
            $counts[$cur->toDateString()] = 0;
            $cur->addDay();
        }

        foreach ($q->get() as $row) {
            $d = Carbon::parse($row->starts_at)->utc()->setTimezone($tz)->toDateString();
            $counts[$d] = ($counts[$d] ?? 0) + 1;
        }

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'timezone' => $tz,
            'counts' => $counts,
        ]);
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            // date = локальная дата организации (Y-m-d)
            'date' => ['nullable', 'date_format:Y-m-d'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'staff_id' => ['nullable', 'integer'],
            'staff_null' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['pending', 'completed', 'cancelled'])],
        ]);

        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $org = User::query()->findOrFail($orgId);
        $tz = $org->timezone ?: 'Europe/Warsaw';

        $q = Visit::query()
            ->where('user_id', $orgId)
            ->with(['service', 'staff:id,name,deleted_at'])
            ->orderBy('starts_at', 'desc');

        // Staff user can only see own visits
        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            $q->where('staff_id', (int)$staff->id);

            // If request tries to specify another staff_id -> forbidden
            if (!empty($data['staff_id']) && (int)$data['staff_id'] !== (int)$staff->id) {
                return response()->json(['message' => 'access_denied'], 403);
            }
            if (!empty($data['staff_null'])) {
                return response()->json(['message' => 'access_denied'], 403);
            }
        }

        // фильтр по локальной дате организации -> диапазон UTC
        if (!empty($data['date'])) {
            $startUtc = Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay()->utc();
            $endUtc   = Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->endOfDay()->utc();

            $q->whereBetween('starts_at', [$startUtc, $endUtc])
                ->reorder('starts_at', 'asc');
        }

        // Optional range filter by org-local days.
        if (empty($data['date']) && (!empty($data['from']) || !empty($data['to']))) {
            $fromLocal = !empty($data['from'])
                ? Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay()
                : null;
            $toLocal = !empty($data['to'])
                ? Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->endOfDay()
                : null;

            if ($fromLocal && $toLocal && $toLocal->lt($fromLocal)) {
                [$fromLocal, $toLocal] = [$toLocal->startOfDay(), $fromLocal->endOfDay()];
            }

            $fromUtc = $fromLocal ? (clone $fromLocal)->utc() : null;
            $toUtc = $toLocal ? (clone $toLocal)->utc() : null;

            if ($fromUtc && $toUtc) {
                $q->whereBetween('starts_at', [$fromUtc, $toUtc]);
            } elseif ($fromUtc) {
                $q->where('starts_at', '>=', $fromUtc);
            } elseif ($toUtc) {
                $q->where('starts_at', '<=', $toUtc);
            }
        }

        // staff filter (owner mode)
        if (!empty($data['staff_null'])) {
            $q->whereNull('staff_id');
        } elseif (!empty($data['staff_id'])) {
            $staffId = (int)$data['staff_id'];
            Staff::query()->where('id', $staffId)->where('user_id', $orgId)->firstOrFail();
            $q->where('staff_id', $staffId);
        }

        if (!empty($data['client_id'])) {
            $clientId = (int)$data['client_id'];
            Client::query()->where('id', $clientId)->where('user_id', $orgId)->firstOrFail();
            $q->where('client_id', $clientId);
        }

        if (!empty($data['status'])) {
            $q->where('status', $data['status']);
        }

        return $q->get();
    }

    public function show(Request $request, Visit $visit)
    {
        $this->authorizeVisit($request, $visit);
        return $visit->load(['service', 'photos', 'staff:id,name,deleted_at']);
    }

    public function agreement(Request $request, Visit $visit)
    {
        $this->authorizeVisit($request, $visit);
        $visit->loadMissing(['service:id,agreement_text']);

        $row = VisitAgreement::query()->where('visit_id', $visit->id)->first();

        $text = $row?->agreement_text;
        if ($text === null || trim($text) === '') {
            $text = $visit->service?->agreement_text;
        }

        return response()->json([
            'visit_id' => $visit->id,
            'service_id' => $visit->service_id,
            'agreement_text' => $text,
            'signature_url' => $row?->signatureUrl(),
            'signed_at' => $row?->signed_at?->toISOString(),
            'updated_at' => $row?->updated_at?->toISOString(),
        ]);
    }

    public function signAgreement(Request $request, Visit $visit)
    {
        $this->authorizeVisit($request, $visit);

        $u = $request->user();
        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'edit_request');
        }

        $data = $request->validate([
            'signature' => ['required', 'image', 'max:5120'],
        ]);

        $visit->loadMissing(['service:id,agreement_text']);

        $row = VisitAgreement::query()->firstOrNew(['visit_id' => $visit->id]);
        if (!$row->exists) {
            $row->service_id = $visit->service_id;
        }

        // Snapshot agreement text at signing time (preserve history).
        if (!$row->agreement_text || trim((string)$row->agreement_text) === '') {
            $t = $visit->service?->agreement_text;
            if (!$t || trim((string)$t) === '') {
                return response()->json(['message' => 'agreement_missing'], 422);
            }
            $row->agreement_text = $t;
        }

        $file = $data['signature'];
        $path = $file->store("visit_agreements/{$visit->id}", 'public');

        // If re-signing, keep old file but replace pointer.
        $row->signature_path = $path;
        $row->signed_at = now();
        $row->save();

        return response()->json([
            'visit_id' => $visit->id,
            'service_id' => $visit->service_id,
            'agreement_text' => $row->agreement_text,
            'signature_url' => $row->signatureUrl(),
            'signed_at' => $row->signed_at?->toISOString(),
            'updated_at' => $row->updated_at?->toISOString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'client_id' => ['nullable', 'integer'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:255'],

            // ВАЖНО: теперь Flutter шлёт локальное время организации
            'starts_at_local' => ['required', 'date_format:Y-m-d H:i'],

            'duration_min' => ['required', 'integer', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $org = User::query()->findOrFail($orgId);
        $tz = $org->timezone ?: 'Europe/Warsaw';

        // ownership
        $service = Service::query()
            ->where('id', $data['service_id'])
            ->where('user_id', $orgId)
            ->firstOrFail();

        // Staff user can only create visits for themselves
        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'create_request');
            $data['staff_id'] = (int)$staff->id;
        }

        if (!empty($data['staff_id'])) {
            $staffModel = Staff::query()->where('id', $data['staff_id'])->where('user_id', $orgId)->firstOrFail();
            // Ensure staff can perform this service.
            $allowed = $staffModel->services()->where('services.id', $service->id)->exists();
            if (!$allowed) {
                return response()->json(['message' => 'service_not_assigned'], 422);
            }
        }

        if (!empty($data['client_id'])) {
            Client::query()->where('id', $data['client_id'])->where('user_id', $orgId)->firstOrFail();
        }

        // локальное время организации -> UTC
        $startsUtc = Carbon::createFromFormat('Y-m-d H:i', $data['starts_at_local'], $tz)->utc();
        $this->assertStaffNotTimeOff(
            orgId: $orgId,
            staffId: $data['staff_id'] ?? null,
            startsAtLocal: $data['starts_at_local'],
            tz: $tz,
        );

        $durationMin = max((int)$data['duration_min'], 0);
        $occupyMin = max($durationMin, 10);
        $endsUtc = (clone $startsUtc)->addMinutes($occupyMin);

        $this->assertNoOverlapWithBuffers(
            orgId: $orgId,
            staffId: $data['staff_id'] ?? null,
            startsUtc: $startsUtc,
            endsUtc: $endsUtc,
            service: $service,
            ignoreVisitId: null,
        );

        $visit = Visit::create([
            'user_id' => $orgId,
            'service_id' => $data['service_id'],
            'staff_id' => $data['staff_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'client_name' => $data['client_name'] ?? null,
            'client_phone' => $data['client_phone'] ?? null,
            'starts_at' => $startsUtc,
            'ends_at' => $endsUtc,
            'duration_min' => $durationMin,
            'price' => $data['price'] ?? ($service->price_fixed ?? $service->price_from ?? null),
            'promo_code_id' => null,
            'promo_code' => null,
            'promo_discount' => 0,
            'status' => 'pending',
        ]);

        // Apply promo code (if any) and overwrite price accordingly.
        $promoCode = PromoCodes::norm($data['promo_code'] ?? '');
        if ($promoCode !== '') {
            $promo = PromoCode::query()
                ->where('user_id', $orgId)
                ->where('code', $promoCode)
                ->where('active', true)
                ->first();

            $base = (float)($visit->price ?? 0);
            $res = PromoCodes::apply(
                org: $org,
                promo: $promo,
                service: $service,
                basePrice: $base,
                localDateYmd: Carbon::createFromFormat('Y-m-d H:i', $data['starts_at_local'], $tz)->toDateString(),
            );

            if ($res['ok'] && $res['promo']) {
                $visit->promo_code_id = (int)$res['promo']->id;
                $visit->promo_code = $promoCode;
                $visit->promo_discount = (float)$res['discount'];
                $visit->price = (float)$res['final'];
                $visit->save();
            }
        }

        return response()->json($visit->load(['service']), 201);
    }

    public function update(Request $request, Visit $visit)
    {
        $this->authorizeVisit($request, $visit);
        $prevStatus = (string)($visit->status ?? '');

        // Support both single file (photo_before) and multiple files (photo_before[]).
        $rules = [
            'status'          => ['nullable', Rule::in(['pending', 'completed', 'cancelled'])],
            'comment'         => ['nullable', 'string'],
            'service_id'      => ['nullable', 'integer'],
            'staff_id'        => ['nullable', 'integer'],
            'staff_null'      => ['nullable', 'boolean'],
            'price'           => ['nullable', 'numeric', 'min:0'],
            'starts_at_local' => ['nullable', 'date_format:Y-m-d H:i'], // org-local time from Flutter
            'promo_code'      => ['nullable', 'string', 'max:40'],
        ];

        $before = $request->file('photo_before');
        if (is_array($before)) {
            $rules['photo_before'] = ['nullable', 'array'];
            $rules['photo_before.*'] = ['image', 'max:5120'];
        } else {
            $rules['photo_before'] = ['nullable', 'image', 'max:5120'];
        }

        $after = $request->file('photo_after');
        if (is_array($after)) {
            $rules['photo_after'] = ['nullable', 'array'];
            $rules['photo_after.*'] = ['image', 'max:5120'];
        } else {
            $rules['photo_after'] = ['nullable', 'image', 'max:5120'];
        }

        $data = $request->validate($rules);

        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;
        $staff = null;
        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
        }

        // Owner can change staff assignment; staff user cannot.
        if ($staff && array_key_exists('staff_id', $data)) {
            return response()->json(['message' => 'access_denied'], 403);
        }
        if ($staff && array_key_exists('staff_null', $data)) {
            return response()->json(['message' => 'access_denied'], 403);
        }

        if (array_key_exists('service_id', $data) && !empty($data['service_id'])) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');
            $newService = Service::query()
                ->where('id', $data['service_id'])
                ->where('user_id', $orgId)
                ->firstOrFail();

            $visit->service_id = (int) $data['service_id'];
            // Keep for overlap check below
            $visit->setRelation('service', $newService);
        }

        // Change staff assignment (owner only). Null means "owner self" booking.
        if (!empty($data['staff_null'])) {
            $visit->staff_id = null;
        } elseif (array_key_exists('staff_id', $data)) {
            $nextStaffId = $data['staff_id'] ?? null;
            if ($nextStaffId !== null) {
                $staffModel = Staff::query()->where('id', (int)$nextStaffId)->where('user_id', $orgId)->firstOrFail();
                // Ensure staff can perform the (possibly updated) service.
                $svc = $visit->relationLoaded('service') ? $visit->service : $visit->service()->first();
                if ($svc) {
                    $allowed = $staffModel->services()->where('services.id', $svc->id)->exists();
                    if (!$allowed) {
                        return response()->json(['message' => 'service_not_assigned'], 422);
                    }
                }
            }

            $visit->staff_id = $nextStaffId === null ? null : (int)$nextStaffId;
        }

        if (array_key_exists('price', $data)) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');
            $visit->price = $data['price'];
        }

        // Promo code apply/clear (owner or staff with edit_request if they can change price/comment; keep simple).
        if (array_key_exists('promo_code', $data)) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');

            $org = \App\Models\User::query()->findOrFail($orgId);
            $svc = $visit->relationLoaded('service') ? $visit->service : $visit->service()->first();

            $incoming = PromoCodes::norm($data['promo_code'] ?? '');

            // Determine base price: if request sets explicit price -> use it; else restore from previous discount if any.
            $base = null;
            if (array_key_exists('price', $data)) {
                $base = (float)($data['price'] ?? 0);
            } else {
                $p = (float)($visit->price ?? 0);
                $d = (float)($visit->promo_discount ?? 0);
                $base = $d > 0 ? ($p + $d) : $p;
            }

            if ($incoming === '') {
                // clear promo, revert to base price
                $visit->promo_code_id = null;
                $visit->promo_code = null;
                $visit->promo_discount = 0;
                $visit->price = $base;
            } else {
                $promo = PromoCode::query()
                    ->where('user_id', $orgId)
                    ->where('code', $incoming)
                    ->where('active', true)
                    ->first();

                $localDate = null;
                if (!empty($data['starts_at_local'])) {
                    $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');
                    $localDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $data['starts_at_local'], $tz)->toDateString();
                } else {
                    $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');
                    $localDate = \Carbon\Carbon::parse($visit->starts_at)->utc()->setTimezone($tz)->toDateString();
                }

                $res = PromoCodes::apply(
                    org: $org,
                    promo: $promo,
                    service: $svc,
                    basePrice: (float)$base,
                    localDateYmd: $localDate,
                );

                if ($res['ok'] && $res['promo']) {
                    $visit->promo_code_id = (int)$res['promo']->id;
                    $visit->promo_code = $incoming;
                    $visit->promo_discount = (float)$res['discount'];
                    $visit->price = (float)$res['final'];
                } else {
                    // invalid -> clear promo but keep base
                    $visit->promo_code_id = null;
                    $visit->promo_code = null;
                    $visit->promo_discount = 0;
                    $visit->price = $base;
                }
            }
        }

        if (array_key_exists('status', $data)) {
            if ($staff) {
                if ($data['status'] === 'completed') {
                    StaffGuard::requirePermission($staff, 'close_request');
                } elseif ($data['status'] === 'cancelled') {
                    StaffGuard::requirePermission($staff, 'cancel_request');
                } elseif ($data['status'] === 'pending') {
                    StaffGuard::requirePermission($staff, 'edit_request');
                }
            }
            $visit->status = $data['status'] ?? $visit->status;
        }

        if (array_key_exists('comment', $data)) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');
            $visit->comment = $data['comment'];
        }

        // Change date/time (org-local -> UTC)
        if (!empty($data['starts_at_local'])) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');
            $org = \App\Models\User::query()->findOrFail($orgId);
            $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');

            $this->assertStaffNotTimeOff(
                orgId: $orgId,
                staffId: $visit->staff_id,
                startsAtLocal: $data['starts_at_local'],
                tz: $tz,
            );

            $startsUtc = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $data['starts_at_local'], $tz)->utc();

            $occupyMin = max((int) ($visit->duration_min ?? 0), 10); // same rule as Flutter slot calc
            $endsUtc = (clone $startsUtc)->addMinutes($occupyMin);

            $visit->starts_at = $startsUtc;
            $visit->ends_at = $endsUtc;
        }

        // If staff assignment changed (or set) without changing time, still enforce time off.
        if ((array_key_exists('staff_id', $data) || array_key_exists('staff_null', $data)) && $visit->status !== 'cancelled') {
            $org = \App\Models\User::query()->findOrFail($orgId);
            $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');
            $local = Carbon::parse($visit->starts_at)->utc()->setTimezone($tz)->format('Y-m-d H:i');
            $this->assertStaffNotTimeOff(
                orgId: $orgId,
                staffId: $visit->staff_id,
                startsAtLocal: $local,
                tz: $tz,
            );
        }

        if ($request->hasFile('photo_before')) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');
            $files = $request->file('photo_before');
            if (!is_array($files)) $files = [$files];
            foreach ($files as $f) {
                if (!$f) continue;
                $path = $f->store("visits/{$visit->id}", 'public');
                \App\Models\VisitPhoto::query()->create([
                    'visit_id' => $visit->id,
                    'kind' => 'before',
                    'path' => $path,
                ]);
            }
        }

        if ($request->hasFile('photo_after')) {
            if ($staff) StaffGuard::requirePermission($staff, 'edit_request');
            $files = $request->file('photo_after');
            if (!is_array($files)) $files = [$files];
            foreach ($files as $f) {
                if (!$f) continue;
                $path = $f->store("visits/{$visit->id}", 'public');
                \App\Models\VisitPhoto::query()->create([
                    'visit_id' => $visit->id,
                    'kind' => 'after',
                    'path' => $path,
                ]);
            }
        }

        // Buffer collision check if time/service changed and visit is not cancelled
        if ($visit->status !== 'cancelled' && ($visit->staff_id || !$staff)) {
            $svc = $visit->relationLoaded('service') ? $visit->service : $visit->service()->first();
            if ($svc) {
                $this->assertNoOverlapWithBuffers(
                    orgId: $orgId,
                    staffId: $visit->staff_id,
                    startsUtc: Carbon::parse($visit->starts_at)->utc(),
                    endsUtc: Carbon::parse($visit->ends_at)->utc(),
                    service: $svc,
                    ignoreVisitId: $visit->id,
                );
            }
        }

        $visit->save();

        // Trigger marketing automation: thank you / review request after visit completion.
        if ($prevStatus !== 'completed' && $visit->status === 'completed') {
            $automationKey = 'thanks_after_visit';
            $automation = MarketingAutomation::query()
                ->where('user_id', $orgId)
                ->where('key', $automationKey)
                ->first();

            if ($automation && $automation->enabled) {
                $delayMin = (int)($automation->delay_min ?? 0);
                $job = new SendMarketingAutomationSms(orgId: (int)$orgId, visitId: (int)$visit->id, automationKey: $automationKey);
                if ($delayMin > 0) {
                    dispatch($job)->delay(now()->addMinutes($delayMin));
                } else {
                    dispatch($job);
                }
            }
        }

        return $visit->fresh()->load(['service', 'photos', 'staff:id,name,deleted_at']);
    }

    private function authorizeVisit(Request $request, Visit $visit): void
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        if ($visit->user_id !== $orgId) {
            abort(404);
        }

        // Staff can only access own visits
        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            if ((int)$visit->staff_id !== (int)$staff->id) {
                abort(404);
            }
        }
    }

    private function assertNoOverlapWithBuffers(
        int $orgId,
        $staffId,
        Carbon $startsUtc,
        Carbon $endsUtc,
        Service $service,
        ?int $ignoreVisitId,
    ): void {
        $bufBefore = (int)($service->buffer_before_min ?? 0);
        $bufAfter = (int)($service->buffer_after_min ?? 0);

        $busyStart = (clone $startsUtc)->subMinutes($bufBefore);
        $busyEnd = (clone $endsUtc)->addMinutes($bufAfter);

        $q = Visit::query()
            ->where('user_id', $orgId)
            ->where('status', '!=', 'cancelled')
            ->with(['service:id,buffer_before_min,buffer_after_min'])
            ->whereBetween('starts_at', [(clone $busyStart)->subDay(), (clone $busyEnd)->addDay()]);

        if ($ignoreVisitId) {
            $q->where('id', '!=', $ignoreVisitId);
        }

        // IMPORTANT:
        // - staff_id is a "resource". Overlaps must be checked per staff (or owner slot).
        // - when staffId is null => owner self bookings (staff_id IS NULL) should not
        //   conflict with staff bookings.
        if ($staffId === null) {
            $q->whereNull('staff_id');
        } else {
            $q->where('staff_id', (int)$staffId);
        }

        foreach ($q->get() as $v) {
            $s = $v->service;
            $sBefore = (int)($s->buffer_before_min ?? 0);
            $sAfter = (int)($s->buffer_after_min ?? 0);

            $vStart = Carbon::parse($v->starts_at)->utc()->subMinutes($sBefore);
            $vEnd = Carbon::parse($v->ends_at)->utc()->addMinutes($sAfter);

            if ($busyStart->lt($vEnd) && $busyEnd->gt($vStart)) {
                abort(response()->json(['message' => 'time_not_available'], 409));
            }
        }
    }

    private function assertStaffNotTimeOff(int $orgId, $staffId, string $startsAtLocal, string $tz): void
    {
        if (empty($staffId)) return;
        if (!Schema::hasTable('staff_time_offs')) return;

        try {
            $local = Carbon::createFromFormat('Y-m-d H:i', $startsAtLocal, $tz);
            $day = $local->toDateString();
        } catch (\Throwable $e) {
            return;
        }

        $exists = StaffTimeOff::query()
            ->where('staff_id', (int)$staffId)
            ->where('date', $day)
            ->exists();

        if ($exists) {
            abort(422, 'staff_unavailable');
        }
    }
}
