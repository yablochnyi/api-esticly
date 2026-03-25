<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Support\StaffGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $data = $request->validate([
            // org-local даты (без времени)
            'from' => ['required', 'date_format:Y-m-d'],
            'to'   => ['required', 'date_format:Y-m-d'],
            // optional
            'staff_id' => ['nullable', 'integer'],
        ]);

        $org = User::query()->findOrFail($orgId);
        $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');

        $fromLocal = \Carbon\Carbon::createFromFormat('Y-m-d', $data['from'], $tz)->startOfDay();
        $toLocal   = \Carbon\Carbon::createFromFormat('Y-m-d', $data['to'], $tz)->endOfDay();

        // хранится в БД в UTC
        $fromUtc = $fromLocal->copy()->utc();
        $toUtc   = $toLocal->copy()->utc();

        $q = DB::table('visits')
            ->where('visits.user_id', $orgId)
            ->whereBetween('starts_at', [$fromUtc, $toUtc]);

        // staff-user analytics: only own staff_id
        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            $q->where('staff_id', (int)$staff->id);
        } elseif (!empty($data['staff_id'])) {
            $staffId = (int)$data['staff_id'];
            Staff::query()->where('id', $staffId)->where('user_id', $orgId)->firstOrFail();
            $q->where('staff_id', $staffId);
        }

        // визиты за период (все статусы)
        $visitsCount = (clone $q)->count();

        // revenue/avgCheck только completed
        $completedQ = (clone $q)->where('status', 'completed');
        $completedCount = (clone $completedQ)->count();
        $revenue = (float) ((clone $completedQ)->sum(DB::raw('COALESCE(price, 0)')));

        $avgCheck = $completedCount > 0 ? ($revenue / $completedCount) : 0.0;

        // clients count:
        // - если есть client_id: distinct client_id
        // - если нет client_id, но есть phone hash: distinct phone hash
        // - если вообще пусто: считаем как уникальные визиты (anon)
        $distinctClientIds = (clone $q)->whereNotNull('client_id')->distinct('client_id')->count('client_id');
        if (Schema::hasColumn('visits', 'client_phone_hash')) {
            $distinctPhones = (clone $q)
                ->whereNull('client_id')
                ->whereNotNull('client_phone_hash')
                ->where('client_phone_hash', '!=', '')
                ->distinct('client_phone_hash')
                ->count('client_phone_hash');

            $anonCount = (clone $q)
                ->whereNull('client_id')
                ->where(function ($w) {
                    $w->whereNull('client_phone_hash')->orWhere('client_phone_hash', '=', '');
                })
                ->count();
        } else {
            $distinctPhones = (clone $q)
                ->whereNull('client_id')
                ->whereNotNull('client_phone')
                ->where('client_phone', '!=', '')
                ->distinct('client_phone')
                ->count('client_phone');

            $anonCount = (clone $q)
                ->whereNull('client_id')
                ->where(function ($w) {
                    $w->whereNull('client_phone')->orWhere('client_phone', '=', '');
                })
                ->count();
        }

        $clientsCount = (int)$distinctClientIds + (int)$distinctPhones + (int)$anonCount;

        $days = [];
        $cursor = $fromLocal->copy()->startOfDay();
        while ($cursor->lte($toLocal)) {
            $days[$cursor->toDateString()] = [
                'visits' => 0,
                'cancelled' => 0,
                'revenue' => 0.0,
            ];
            $cursor->addDay();
        }

        $hourLoad = array_fill(0, 24, 0);
        $rows = (clone $q)
            ->select(['starts_at', 'status', 'price'])
            ->orderBy('starts_at')
            ->get();

        foreach ($rows as $row) {
            $local = Carbon::parse($row->starts_at)->utc()->setTimezone($tz);
            $dayKey = $local->toDateString();
            if (!array_key_exists($dayKey, $days)) {
                continue;
            }

            $days[$dayKey]['visits']++;

            if (($row->status ?? null) === 'cancelled') {
                $days[$dayKey]['cancelled']++;
            } else {
                $hour = (int) $local->format('G');
                if ($hour >= 0 && $hour < 24) {
                    $hourLoad[$hour]++;
                }
            }

            if (($row->status ?? null) === 'completed') {
                $days[$dayKey]['revenue'] += (float) ($row->price ?? 0);
            }
        }

        // services unique
        $servicesUnique = (clone $q)->distinct('service_id')->count('service_id');

        // top services (по количеству визитов), + revenue по completed
        $top = (clone $q)
            ->leftJoin('services', function ($join) use ($orgId) {
                $join->on('services.id', '=', 'visits.service_id')
                    ->where('services.user_id', '=', $orgId);
            })
            ->selectRaw('visits.service_id as service_id')
            ->selectRaw('COALESCE(services.name, CONCAT("Service #", visits.service_id)) as name')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('SUM(CASE WHEN visits.status = "completed" THEN COALESCE(visits.price, 0) ELSE 0 END) as revenue')
            ->groupBy('visits.service_id', 'services.name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'service_id' => (int)$r->service_id,
                'name'       => (string)$r->name,
                'count'      => (int)$r->cnt,
                'revenue'    => (float)$r->revenue,
            ])
            ->values();

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'timezone' => $tz,

            'clients' => $clientsCount,
            'visits' => (int)$visitsCount,
            'revenue' => $revenue,
            'avg_check' => (float)$avgCheck,
            'revenue_by_day' => array_map(fn ($key, $item) => [
                'date' => $key,
                'value' => round((float) $item['revenue'], 2),
            ], array_keys($days), array_values($days)),
            'visits_by_day' => array_map(fn ($key, $item) => [
                'date' => $key,
                'value' => (int) $item['visits'],
            ], array_keys($days), array_values($days)),
            'cancellations_by_day' => array_map(fn ($key, $item) => [
                'date' => $key,
                'value' => (int) $item['cancelled'],
            ], array_keys($days), array_values($days)),
            'hour_load' => array_values($hourLoad),

            'services_unique' => (int)$servicesUnique,
            'top_services' => $top,
        ]);
    }
}
