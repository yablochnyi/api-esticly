<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Visit;
use App\Support\StaffGuard;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        $q = Client::query()
            ->where('user_id', $orgId)
            ->select(['id','name','phone','instagram','blocked_at']);

        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'clients_access');

            // Backward compatible: created_by_staff_id may not exist yet (migration pending).
            if (Schema::hasColumn('clients', 'created_by_staff_id')) {
                // Staff sees only "their" clients:
                // - clients created by this staff member
                // - clients that already have at least one visit with this staff member
                $q->where(function ($sub) use ($orgId, $staff) {
                    $sub->where('created_by_staff_id', (int)$staff->id)
                        ->orWhereExists(function ($v) use ($orgId, $staff) {
                            $v->select(DB::raw(1))
                                ->from('visits')
                                ->whereColumn('visits.client_id', 'clients.id')
                                ->where('visits.user_id', $orgId)
                                ->where('visits.staff_id', (int)$staff->id)
                                ->whereNotNull('visits.client_id')
                                ->where('visits.status', '!=', 'cancelled');
                        });
                });
            } else {
                // Old behavior: only clients that already have at least one visit with this staff.
                $clientIds = Visit::query()
                    ->where('user_id', $orgId)
                    ->where('staff_id', (int)$staff->id)
                    ->whereNotNull('client_id')
                    ->distinct()
                    ->pluck('client_id')
                    ->all();
                $q->whereIn('id', $clientIds);
            }
        }

        if ($request->boolean('blocked')) {
            $q->whereNotNull('blocked_at');
        }

        $nowUtc = Carbon::now()->utc();

        // Sleepers: clients who haven't had any visits in the last 3+ months (and no future visits).
        // Includes clients who never visited.
        if ($request->boolean('sleepers')) {
            $cutoff = (clone $nowUtc)->subMonths(3);

            // Exclude anyone with a future visit
            $q->whereNotExists(function ($sub) use ($orgId, $nowUtc) {
                $sub->select(DB::raw(1))
                    ->from('visits')
                    ->whereColumn('visits.client_id', 'clients.id')
                    ->where('visits.user_id', $orgId)
                    ->whereNotNull('visits.client_id')
                    ->where('visits.status', '!=', 'cancelled')
                    ->where('visits.starts_at', '>', $nowUtc);
            });

            // Exclude anyone with a recent past visit within 3 months
            $q->whereNotExists(function ($sub) use ($orgId, $cutoff, $nowUtc) {
                $sub->select(DB::raw(1))
                    ->from('visits')
                    ->whereColumn('visits.client_id', 'clients.id')
                    ->where('visits.user_id', $orgId)
                    ->whereNotNull('visits.client_id')
                    ->where('visits.status', '!=', 'cancelled')
                    ->whereBetween('visits.starts_at', [$cutoff, $nowUtc]);
            });
        }

        return $q->orderBy('name')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'phone' => $c->phone,
            'instagram' => $c->instagram,
            'blocked' => !is_null($c->blocked_at),
        ]);
    }

    public function store(Request $request)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'clients_access');
        }

        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'phone' => ['nullable','string','max:50'],
            'instagram' => ['nullable','string','max:255'],
        ]);

        $org = \App\Models\User::query()->findOrFail($orgId);
        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            if (Schema::hasColumn('clients', 'created_by_staff_id')) {
                $data['created_by_staff_id'] = (int)$staff->id;
            }
        }
        $client = $org->clients()->create($data);

        return response()->json($client->only(['id','name','phone','instagram']), 201);
    }

    public function show(Request $request, Client $client)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        abort_unless($client->user_id === $orgId, 404);

        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'clients_access');

            if (Schema::hasColumn('clients', 'created_by_staff_id')) {
                $allowed = ($client->created_by_staff_id && (int)$client->created_by_staff_id === (int)$staff->id)
                    || Visit::query()
                        ->where('user_id', $orgId)
                        ->where('staff_id', (int)$staff->id)
                        ->where('client_id', $client->id)
                        ->exists();
            } else {
                $allowed = Visit::query()
                    ->where('user_id', $orgId)
                    ->where('staff_id', (int)$staff->id)
                    ->where('client_id', $client->id)
                    ->exists();
            }
            abort_unless($allowed, 404);
        }

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'instagram' => $client->instagram, // если поле есть
            'blocked' => !is_null($client->blocked_at),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        abort_unless($client->user_id === $orgId, 404);

        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            StaffGuard::requirePermission($staff, 'clients_access');

            if (Schema::hasColumn('clients', 'created_by_staff_id')) {
                $allowed = ($client->created_by_staff_id && (int)$client->created_by_staff_id === (int)$staff->id)
                    || Visit::query()
                        ->where('user_id', $orgId)
                        ->where('staff_id', (int)$staff->id)
                        ->where('client_id', $client->id)
                        ->exists();
            } else {
                $allowed = Visit::query()
                    ->where('user_id', $orgId)
                    ->where('staff_id', (int)$staff->id)
                    ->where('client_id', $client->id)
                    ->exists();
            }
            abort_unless($allowed, 404);
        }

        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'phone' => ['sometimes','nullable','string','max:50'],
            'instagram' => ['sometimes','nullable','string','max:255'], // если поле есть
            'blocked' => ['sometimes','boolean'],
        ]);

        if (array_key_exists('blocked', $data)) {
            $client->blocked_at = $data['blocked'] ? now() : null;
        }

        $client->fill(collect($data)->except('blocked')->toArray());
        $client->save();

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'phone' => $client->phone,
            'instagram' => $client->instagram,
            'blocked' => !is_null($client->blocked_at),
        ]);
    }
}
