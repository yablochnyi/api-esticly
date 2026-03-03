<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StaffGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $orgId = $user->organization_id ?? $user->id;

        $org = User::query()->findOrFail($orgId);
        $tz = $org->timezone ?: 'Europe/Warsaw';

        $localToday = Carbon::now($tz)->toDateString(); // Y-m-d in org tz
        $startUtc = Carbon::createFromFormat('Y-m-d', $localToday, $tz)->startOfDay()->utc();
        $endUtc = Carbon::createFromFormat('Y-m-d', $localToday, $tz)->endOfDay()->utc();

        $visitsQ = DB::table('visits')
            ->where('user_id', $orgId)
            ->whereBetween('starts_at', [$startUtc, $endUtc]);

        $clientsCount = null;

        if ($user->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            $visitsQ->where('staff_id', (int)$staff->id);

            // Only count clients that staff can actually access.
            $clientsAccess = (bool)($staff->permissions['clients_access'] ?? false);
            if ($clientsAccess) {
                $clientsCount = (int) DB::table('visits')
                    ->where('user_id', $orgId)
                    ->where('staff_id', (int)$staff->id)
                    ->whereNotNull('client_id')
                    ->distinct()
                    ->count('client_id');
            } else {
                $clientsCount = 0;
            }
        } else {
            $clientsCount = (int) DB::table('clients')
                ->where('user_id', $orgId)
                ->whereNull('deleted_at')
                ->count();
        }

        $todayVisitsCount = (int) $visitsQ->count();

        $reviewsCount = (int) DB::table('reviews')
            ->where('user_id', $orgId)
            ->count();
        $ratingAvg = (float) (DB::table('reviews')
            ->where('user_id', $orgId)
            ->avg('rating') ?? 0);

        return response()->json([
            'date_local' => $localToday,
            'timezone' => $tz,
            'clients_count' => $clientsCount,
            'today_visits_count' => $todayVisitsCount,
            'rating_avg' => round($ratingAvg, 2),
            'reviews_count' => $reviewsCount,
        ]);
    }
}

