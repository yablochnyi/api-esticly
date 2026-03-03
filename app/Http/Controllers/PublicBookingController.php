<?php

namespace App\Http\Controllers;

use App\Jobs\SendBookingCreatedPush;
use App\Models\Client;
use App\Models\PortfolioPhoto;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\Visit;
use App\Support\PhoneIndex;
use App\Support\PublicLocale;
use App\Support\PromoCodes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PublicBookingController extends Controller
{
    private function orgBySlugOr404(string $slug): User
    {
        return User::query()->where('booking_slug', $slug)->firstOrFail();
    }

    private function tz(User $org): string
    {
        return $org->timezone ?: (config('app.timezone') ?: 'UTC');
    }

    private function scheduleFor(User $org, Carbon $localDay): ?array
    {
        $raw = $org->schedule;
        if (!is_array($raw)) return null;

        $key = match ((int)$localDay->dayOfWeekIso) {
            1 => 'mon',
            2 => 'tue',
            3 => 'wed',
            4 => 'thu',
            5 => 'fri',
            6 => 'sat',
            7 => 'sun',
            default => 'mon',
        };

        $d = $raw[$key] ?? null;
        if (!is_array($d)) return null;
        $enabled = (bool)($d['enabled'] ?? false);
        $start = (string)($d['start'] ?? '07:00');
        $end = (string)($d['end'] ?? '16:00');
        return ['enabled' => $enabled, 'start' => $start, 'end' => $end, 'key' => $key];
    }

    private function durationMin(Service $s): int
    {
        $d = (int)($s->duration_from_min ?? 0);
        $t = (int)($s->duration_to_min ?? 0);
        if ($d > 0) return $d;
        if ($t > 0) return $t;
        return 0;
    }

    private function phoneDigits(?string $phone): string
    {
        $phone = trim((string)$phone);
        $digits = preg_replace('/\D+/', '', $phone);
        return $digits ?: '';
    }

    private function phoneVariants(string $phone): array
    {
        $phone = trim($phone);
        $digits = $this->phoneDigits($phone);
        $variants = array_values(array_unique(array_filter([
            $phone,
            $digits,
            $digits ? ('+' . $digits) : null,
        ])));
        return $variants;
    }

    private function findClientByPhone(User $org, string $phone): ?Client
    {
        if (!Schema::hasColumn('clients', 'phone_hash')) {
            $variants = $this->phoneVariants($phone);
            return Client::query()
                ->where('user_id', $org->id)
                ->whereIn('phone', $variants)
                ->orderByDesc('id')
                ->first();
        }

        $phoneHash = PhoneIndex::hash($phone);
        if (!$phoneHash) {
            return null;
        }

        return Client::query()
            ->where('user_id', $org->id)
            ->where('phone_hash', $phoneHash)
            ->orderByDesc('id')
            ->first();
    }

    private function isStaffTimeOff(int $staffId, string $dateYmd): bool
    {
        if (!Schema::hasTable('staff_time_offs')) return false;
        return DB::table('staff_time_offs')
            ->where('staff_id', $staffId)
            ->where('date', $dateYmd)
            ->exists();
    }

    private function staffForService(User $org, int $serviceId, ?string $dateYmd = null)
    {
        // Ensure service belongs to org.
        Service::query()->where('id', $serviceId)->where('user_id', $org->id)->firstOrFail();

        $q = Staff::query()
            ->where('user_id', $org->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereIn('id', function ($sub) use ($serviceId) {
                $sub->select('staff_id')->from('service_staff')->where('service_id', $serviceId);
            })
            ->orderBy('name');

        if ($dateYmd && Schema::hasTable('staff_time_offs')) {
            $q->whereNotIn('id', function ($sub) use ($dateYmd) {
                $sub->select('staff_id')->from('staff_time_offs')->where('date', $dateYmd);
            });
        }

        return $q->get(['id', 'name']);
    }

    private function staffIdOrNull($raw): ?int
    {
        if ($raw === null || $raw === '') return null;
        $v = (int)$raw;
        return $v > 0 ? $v : null;
    }

    private function dayHasAnyFreeSlot(
        User $org,
        Service $service,
        Carbon $dayLocal,
        array $visits,
        int $occupyMin,
        int $candBefore,
        int $candAfter
    ): bool {
        $sch = $this->scheduleFor($org, $dayLocal);
        if (!$sch || !$sch['enabled']) return false;

        [$sh, $sm] = array_pad(explode(':', $sch['start']), 2, '0');
        [$eh, $em] = array_pad(explode(':', $sch['end']), 2, '0');
        $startLocal = (clone $dayLocal)->setTime((int)$sh, (int)$sm, 0);
        $endLocal = (clone $dayLocal)->setTime((int)$eh, (int)$em, 0);
        if (!$endLocal->greaterThan($startLocal)) return false;

        $stepMin = 10;
        for ($t = clone $startLocal; $t->lessThan($endLocal); $t->addMinutes($stepMin)) {
            $endT = (clone $t)->addMinutes($occupyMin);
            if ($endT->greaterThan($endLocal)) break;

            $candStartUtc = (clone $t)->subMinutes($candBefore)->utc();
            $candEndUtc = (clone $endT)->addMinutes($candAfter)->utc();

            $free = true;
            foreach ($visits as $v) {
                $s = $v['service'] ?? null;
                $vBefore = (int)($s['buffer_before_min'] ?? 0);
                $vAfter = (int)($s['buffer_after_min'] ?? 0);

                $vStart = Carbon::parse($v['starts_at'])->utc()->subMinutes($vBefore);
                $vEnd = Carbon::parse($v['ends_at'])->utc()->addMinutes($vAfter);

                if ($candStartUtc->lt($vEnd) && $candEndUtc->gt($vStart)) {
                    $free = false;
                    break;
                }
            }
            if ($free) return true;
        }
        return false;
    }

    public function landing(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);
        $lang = PublicLocale::resolve($request, (string)$org->language_code);
        App::setLocale($lang);

        $services = Service::query()
            ->where('user_id', $org->id)
            ->orderBy('name')
            ->get();

        $tz = $this->tz($org);
        $schedule = is_array($org->schedule) ? $org->schedule : [];
        $scheduleRows = [];
        $dayLabels = [
            'mon' => __('booking.days.mon'),
            'tue' => __('booking.days.tue'),
            'wed' => __('booking.days.wed'),
            'thu' => __('booking.days.thu'),
            'fri' => __('booking.days.fri'),
            'sat' => __('booking.days.sat'),
            'sun' => __('booking.days.sun'),
        ];
        foreach ($dayLabels as $k => $label) {
            $d = is_array($schedule) ? ($schedule[$k] ?? null) : null;
            $enabled = is_array($d) ? (($d['enabled'] ?? false) === true) : false;
            $start = is_array($d) ? ($d['start'] ?? '—') : '—';
            $end = is_array($d) ? ($d['end'] ?? '—') : '—';
            $scheduleRows[] = [
                'label' => $label,
                'enabled' => $enabled,
                'start' => $start,
                'end' => $end,
            ];
        }

        $code = $org->currency_code ?: '';
        $serviceCards = [];
        foreach ($services as $s) {
            $dur = (int)($s->duration_from_min ?? $s->duration_to_min ?? 0);
            $priceType = $s->price_type ?? 'fixed';
            if ($priceType === 'range') {
                $p = ($s->price_from ?? '—') . '–' . ($s->price_to ?? '—');
            } else {
                $p = $s->price_fixed ?? '—';
            }
            $serviceCards[] = [
                'name' => $s->name,
                'duration' => $dur,
                'price' => $p,
                'currency' => $code,
            ];
        }

        $portfolioPhotos = PortfolioPhoto::query()
            ->where('user_id', $org->id)
            // for public page show all org photos (including staff-specific)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('booking.landing', [
            'org' => $org,
            'tz' => $tz,
            'services' => $services,
            'schedule' => $schedule,
            'scheduleRows' => $scheduleRows,
            'serviceCards' => $serviceCards,
            'portfolioPhotos' => $portfolioPhotos,
            'lang' => $lang,
        ]);
    }

    public function book(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);
        $lang = PublicLocale::resolve($request, (string)$org->language_code);
        App::setLocale($lang);

        $services = Service::query()
            ->where('user_id', $org->id)
            ->orderBy('name')
            ->get();

        $serviceId = (int)($request->query('service_id') ?? 0);
        $selected = $serviceId > 0 ? $services->firstWhere('id', $serviceId) : null;

        return view('booking.book', [
            'org' => $org,
            'tz' => $this->tz($org),
            'services' => $services,
            'selectedServiceId' => $selected?->id,
            'lang' => $lang,
        ]);
    }

    public function staff(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);

        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $serviceId = (int)$data['service_id'];
        $dateYmd = $data['date'] ?? null;

        $list = $this->staffForService($org, $serviceId, $dateYmd);

        return response()->json([
            'data' => $list->map(fn($s) => ['id' => (int)$s->id, 'name' => (string)$s->name])->values(),
        ]);
    }

    public function availability(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);

        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m'], // org-local month
            'staff_id' => ['nullable', 'integer'], // 0 => salon
        ]);

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('user_id', $org->id)
            ->firstOrFail();

        $staffId = $this->staffIdOrNull($data['staff_id'] ?? null);

        $tz = $this->tz($org);
        $monthLocal = Carbon::createFromFormat('Y-m', $data['month'], $tz)->startOfMonth();
        $fromLocal = (clone $monthLocal)->startOfMonth()->startOfDay();
        $toLocal = (clone $monthLocal)->endOfMonth()->endOfDay();

        $fromUtc = (clone $fromLocal)->utc();
        $toUtc = (clone $toLocal)->utc();

        $occupyMin = max($this->durationMin($service), 10);
        $candBefore = (int)($service->buffer_before_min ?? 0);
        $candAfter = (int)($service->buffer_after_min ?? 0);

        // Fetch visits for this month for selected staff (or salon=staff_id null)
        $visits = Visit::query()
            ->where('user_id', $org->id)
            ->when($staffId, fn($q) => $q->where('staff_id', $staffId), fn($q) => $q->whereNull('staff_id'))
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$fromUtc, $toUtc])
            ->with(['service:id,buffer_before_min,buffer_after_min'])
            ->get()
            ->map(function ($v) {
                return [
                    'starts_at' => $v->starts_at,
                    'ends_at' => $v->ends_at,
                    'service' => [
                        'buffer_before_min' => (int)($v->service?->buffer_before_min ?? 0),
                        'buffer_after_min' => (int)($v->service?->buffer_after_min ?? 0),
                    ],
                ];
            })
            ->all();

        // Group by org-local date for faster checks
        $byDay = [];
        foreach ($visits as $v) {
            $d = Carbon::parse($v['starts_at'])->timezone($tz)->toDateString();
            $byDay[$d][] = $v;
        }

        $available = [];
        $daysInMonth = (int)$monthLocal->daysInMonth;
        for ($i = 0; $i < $daysInMonth; $i++) {
            $day = (clone $monthLocal)->addDays($i)->startOfDay();
            $ymd = $day->toDateString();

            if ($staffId && $this->isStaffTimeOff($staffId, $ymd)) {
                continue;
            }

            $dayVisits = $byDay[$ymd] ?? [];
            if ($this->dayHasAnyFreeSlot($org, $service, $day, $dayVisits, $occupyMin, $candBefore, $candAfter)) {
                $available[] = $ymd;
            }
        }

        return response()->json([
            'month' => $monthLocal->format('Y-m'),
            'timezone' => $tz,
            'available_dates' => $available,
        ]);
    }

    public function slots(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], // org-local date
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'], // 0 => salon
        ]);

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('user_id', $org->id)
            ->firstOrFail();

        $staffId = $this->staffIdOrNull($data['staff_id'] ?? null);
        if ($staffId) {
            $staff = Staff::query()
                ->where('id', $staffId)
                ->where('user_id', $org->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->firstOrFail();

            // Ensure staff can perform this service.
            $can = DB::table('service_staff')
                ->where('service_id', $service->id)
                ->where('staff_id', $staff->id)
                ->exists();
            if (!$can) {
                return response()->json(['times' => []], 200);
            }
        }

        $tz = $this->tz($org);
        $dayLocal = Carbon::createFromFormat('Y-m-d', $data['date'], $tz)->startOfDay();
        $dateYmd = $dayLocal->toDateString();
        if ($staffId && $this->isStaffTimeOff((int)$staffId, $dateYmd)) {
            return response()->json(['times' => [], 'staff_unavailable' => true], 200);
        }
        $sch = $this->scheduleFor($org, $dayLocal);
        if (!$sch || !$sch['enabled']) {
            return response()->json(['times' => []]);
        }

        [$sh, $sm] = array_pad(explode(':', $sch['start']), 2, '0');
        [$eh, $em] = array_pad(explode(':', $sch['end']), 2, '0');
        $startLocal = (clone $dayLocal)->setTime((int)$sh, (int)$sm, 0);
        $endLocal = (clone $dayLocal)->setTime((int)$eh, (int)$em, 0);
        if (!$endLocal->greaterThan($startLocal)) {
            return response()->json(['times' => []]);
        }

        $occupyMin = max($this->durationMin($service), 10);
        $candBefore = (int)($service->buffer_before_min ?? 0);
        $candAfter = (int)($service->buffer_after_min ?? 0);

        // Pull visits for the local day (converted to UTC range)
        $startUtc = (clone $dayLocal)->startOfDay()->utc();
        $endUtc = (clone $dayLocal)->endOfDay()->utc();

        $visits = Visit::query()
            ->where('user_id', $org->id)
            ->when($staffId, fn($q) => $q->where('staff_id', $staffId), fn($q) => $q->whereNull('staff_id'))
            ->whereBetween('starts_at', [$startUtc, $endUtc])
            ->where('status', '!=', 'cancelled')
            ->with(['service:id,buffer_before_min,buffer_after_min'])
            ->get();

        $times = [];
        for ($t = clone $startLocal; $t->lessThan($endLocal); $t->addMinutes(10)) {
            $endT = (clone $t)->addMinutes($occupyMin);
            if ($endT->greaterThan($endLocal)) break;

            // Candidate interval in UTC with buffers.
            $candStartUtc = (clone $t)->subMinutes($candBefore)->utc();
            $candEndUtc = (clone $endT)->addMinutes($candAfter)->utc();

            $free = true;
            foreach ($visits as $v) {
                $s = $v->service;
                $vBefore = (int)($s?->buffer_before_min ?? 0);
                $vAfter = (int)($s?->buffer_after_min ?? 0);

                $vStart = Carbon::parse($v->starts_at)->utc()->subMinutes($vBefore);
                $vEnd = Carbon::parse($v->ends_at)->utc()->addMinutes($vAfter);

                if ($candStartUtc->lt($vEnd) && $candEndUtc->gt($vStart)) {
                    $free = false;
                    break;
                }
            }

            if ($free) {
                $times[] = $t->format('H:i');
            }
        }

        return response()->json([
            'date' => $dayLocal->toDateString(),
            'timezone' => $tz,
            'times' => $times,
        ]);
    }

    public function promoValidate(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);

        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'max:40'],
            'date' => ['nullable', 'date_format:Y-m-d'], // org-local date
        ]);

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('user_id', $org->id)
            ->firstOrFail();

        $base = (float)($service->price_fixed ?? $service->price_from ?? 0);
        $norm = PromoCodes::norm((string)$data['code']);

        $promo = PromoCode::query()
            ->where('user_id', $org->id)
            ->where('code', $norm)
            ->where('active', true)
            ->first();

        $res = PromoCodes::apply(
            org: $org,
            promo: $promo,
            service: $service,
            basePrice: $base,
            localDateYmd: $data['date'] ?? null,
        );

        return response()->json([
            'ok' => (bool)$res['ok'],
            'message' => (string)$res['message'],
            'code' => $norm,
            'base_price' => round($base, 2),
            'discount' => (float)$res['discount'],
            'final_price' => (float)$res['final'],
        ]);
    }

    public function submit(string $slug, Request $request)
    {
        $org = $this->orgBySlugOr404($slug);
        $lang = PublicLocale::resolve($request, (string)$org->language_code);
        App::setLocale($lang);

        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'], // 0 => salon
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'promo_code' => ['nullable', 'string', 'max:40'],
        ]);

        $service = Service::query()
            ->where('id', (int)$data['service_id'])
            ->where('user_id', $org->id)
            ->firstOrFail();

        $staffId = $this->staffIdOrNull($data['staff_id'] ?? null);
        $staff = null;
        if ($staffId) {
            $staff = Staff::query()
                ->where('id', $staffId)
                ->where('user_id', $org->id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->firstOrFail();

            // Ensure staff can perform this service.
            $can = DB::table('service_staff')
                ->where('service_id', $service->id)
                ->where('staff_id', $staff->id)
                ->exists();
            abort_unless($can, 422, 'service_not_assigned');
        }

        // Basic "whitelist-only": require existing client by phone (not blocked).
        if (($org->online_booking_whitelist_only ?? false) === true) {
            $existing = $this->findClientByPhone($org, $data['phone']);
            if (!$existing || $existing->blocked_at) {
                return back()->withErrors(['phone' => 'access_denied'])->withInput();
            }
        }

        $tz = $this->tz($org);
        $local = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $data['time'], $tz);
        if ($staffId && $this->isStaffTimeOff((int)$staffId, $local->toDateString())) {
            abort(409, 'staff_unavailable');
        }
        $startsUtc = (clone $local)->utc();
        $occupyMin = max($this->durationMin($service), 10);
        $endsUtc = (clone $startsUtc)->addMinutes($occupyMin);

        // Reuse VisitController overlap logic by doing a DB-level check inside a tx.
        $createdVisitId = null;
        DB::transaction(function () use ($org, $service, $staffId, $startsUtc, $endsUtc, $data, &$createdVisitId) {
            // Use server-side overlap checker from VisitController by calling same logic:
            // We keep it simple: create will still be rejected later in mobile API if conflict,
            // but here we proactively check with buffers.
            $candBefore = (int)($service->buffer_before_min ?? 0);
            $candAfter = (int)($service->buffer_after_min ?? 0);
            $candStart = (clone $startsUtc)->subMinutes($candBefore);
            $candEnd = (clone $endsUtc)->addMinutes($candAfter);

            $visits = Visit::query()
                ->where('user_id', $org->id)
                ->when($staffId, fn($q) => $q->where('staff_id', $staffId), fn($q) => $q->whereNull('staff_id'))
                ->where('status', '!=', 'cancelled')
                ->where(function ($q) use ($candStart, $candEnd) {
                    $q->where('starts_at', '<', $candEnd)
                        ->where('ends_at', '>', $candStart);
                })
                ->with(['service:id,buffer_before_min,buffer_after_min'])
                ->lockForUpdate()
                ->get();

            foreach ($visits as $v) {
                $s = $v->service;
                $vBefore = (int)($s?->buffer_before_min ?? 0);
                $vAfter = (int)($s?->buffer_after_min ?? 0);
                $vStart = Carbon::parse($v->starts_at)->utc()->subMinutes($vBefore);
                $vEnd = Carbon::parse($v->ends_at)->utc()->addMinutes($vAfter);
                if ($candStart->lt($vEnd) && $candEnd->gt($vStart)) {
                    abort(409, 'time_not_available');
                }
            }

            $digits = $this->phoneDigits($data['phone']);
            $normalizedPhone = $digits ? ('+' . $digits) : trim((string)$data['phone']);

            $client = $this->findClientByPhone($org, $normalizedPhone);
            if (!$client) {
                $client = Client::query()->create([
                    'user_id' => $org->id,
                    'name' => $data['name'],
                    'phone' => $normalizedPhone,
                    'blocked_at' => null,
                ]);
            }

            $visit = Visit::query()->create([
                'user_id' => $org->id,
                'service_id' => $service->id,
                'promo_code_id' => null,
                'promo_code' => null,
                'staff_id' => $staffId,
                'client_id' => $client->id,
                'client_name' => $data['name'],
                'client_phone' => $normalizedPhone,
                'starts_at' => $startsUtc,
                'ends_at' => $endsUtc,
                'duration_min' => max((int)($service->duration_from_min ?? 0), 0),
                'price' => $service->price_fixed ?? $service->price_from ?? null,
                'promo_discount' => 0,
                'status' => 'pending',
                'comment' => Arr::get($data, 'comment'),
            ]);
            $createdVisitId = (int)$visit->id;

            $promoCode = PromoCodes::norm($data['promo_code'] ?? '');
            if ($promoCode !== '') {
                $promo = PromoCode::query()
                    ->where('user_id', $org->id)
                    ->where('code', $promoCode)
                    ->where('active', true)
                    ->first();

                $base = (float)($visit->price ?? 0);
                $res = PromoCodes::apply(
                    org: $org,
                    promo: $promo,
                    service: $service,
                    basePrice: $base,
                    localDateYmd: $data['date'],
                );

                if ($res['ok'] && $res['promo']) {
                    $visit->promo_code_id = (int)$res['promo']->id;
                    $visit->promo_code = $promoCode;
                    $visit->promo_discount = (float)$res['discount'];
                    $visit->price = (float)$res['final'];
                    $visit->save();
                }
            }
        });

        if ($createdVisitId) {
            SendBookingCreatedPush::dispatch((int)$org->id, (int)$createdVisitId);
        }

        return view('booking.done', [
            'org' => $org,
            'lang' => $lang,
        ]);
    }
}
