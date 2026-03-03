<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Staff;
use App\Support\Audit;
use App\Support\StaffGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    private function decodeIds($raw): array
    {
        if (is_array($raw)) {
            $arr = $raw;
        } else {
            $arr = json_decode((string)$raw, true);
        }
        if (!is_array($arr)) return [];
        $out = [];
        foreach ($arr as $v) {
            $id = (int)$v;
            if ($id > 0) $out[] = $id;
        }
        $out = array_values(array_unique($out));
        return $out;
    }

    private function dto(Service $s): array
    {
        $base = $s->only([
            'id','name','description','category',
            'agreement_text',
            'price_type','price_fixed','price_from','price_to',
            'duration_from_min','duration_to_min',
            'buffer_before_min','buffer_after_min',
        ]);
        $ids = $s->relationLoaded('staff') ? $s->staff->pluck('id')->values()->all() : [];
        $base['staff_ids'] = $ids;
        return $base;
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            // staff can see only assigned services (needed for creating/editing own visits)
            $ids = Staff::query()
                ->where('id', (int)$staff->id)
                ->where('user_id', $orgId)
                ->firstOrFail()
                ->services()
                ->pluck('services.id')
                ->all();

            return Service::query()
                ->where('user_id', $orgId)
                ->whereIn('id', $ids)
                ->orderBy('name')
                ->with(['staff:id'])
                ->get([
                    'id','name','description','category',
                    'agreement_text',
                    'price_type','price_fixed','price_from','price_to',
                    'duration_from_min','duration_to_min',
                    'buffer_before_min','buffer_after_min',
                ])
                ->map(fn($s) => $this->dto($s));
        }

        return Service::query()
            ->where('user_id', $orgId)
            ->orderBy('name')
            ->with(['staff:id'])
            ->get([
                'id','name','description','category',
                'agreement_text',
                'price_type','price_fixed','price_from','price_to',
                'duration_from_min','duration_to_min',
                'buffer_before_min','buffer_after_min',
            ])
            ->map(fn($s) => $this->dto($s));
    }

    public function show(Request $request, Service $service)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        abort_unless($service->user_id === $orgId, 404);

        if ($u->staff_id) {
            $staff = StaffGuard::currentOrAbort($request);
            $allowed = Staff::query()
                ->where('id', (int)$staff->id)
                ->where('user_id', $orgId)
                ->firstOrFail()
                ->services()
                ->where('services.id', $service->id)
                ->exists();
            abort_unless($allowed, 404);
        }
        $service->load(['staff:id']);
        return $this->dto($service);
    }

    public function store(Request $request)
    {
        if ($request->user()->staff_id) {
            return response()->json(['message' => 'access_denied'], 403);
        }
        $data = $this->validateService($request);
        $staffIds = $this->decodeIds($request->input('staff_ids', '[]'));

        $service = $request->user()->services()->create($data);
        $beforeStaffIds = [];
        if (!empty($staffIds)) {
            $validStaffIds = Staff::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('id', $staffIds)
                ->pluck('id')
                ->all();
            $service->staff()->sync($validStaffIds);
        } else {
            $service->staff()->sync([]);
        }
        $afterStaffIds = $service->staff()->pluck('staff.id')->all();
        Audit::custom(
            (int) $request->user()->id,
            'service_staff_sync',
            ['service_id' => (int) $service->id, 'staff_ids' => $beforeStaffIds],
            ['service_id' => (int) $service->id, 'staff_ids' => $afterStaffIds],
            ['staff_ids'],
            'api_mobile',
        );

        $service->load(['staff:id']);
        return response()->json($this->dto($service), 201);
    }

    public function update(Request $request, Service $service)
    {
        if ($request->user()->staff_id) {
            return response()->json(['message' => 'access_denied'], 403);
        }
        abort_unless($service->user_id === $request->user()->id, 404);

        $data = $this->validateService($request);
        $staffIds = $this->decodeIds($request->input('staff_ids', '[]'));
        $beforeStaffIds = $service->staff()->pluck('staff.id')->all();
        $service->update($data);

        $validStaffIds = Staff::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $staffIds)
            ->pluck('id')
            ->all();
        $service->staff()->sync($validStaffIds);
        $afterStaffIds = $service->staff()->pluck('staff.id')->all();
        Audit::custom(
            (int) $request->user()->id,
            'service_staff_sync',
            ['service_id' => (int) $service->id, 'staff_ids' => $beforeStaffIds],
            ['service_id' => (int) $service->id, 'staff_ids' => $afterStaffIds],
            ['staff_ids'],
            'api_mobile',
        );

        $service->load(['staff:id']);
        return response()->json($this->dto($service));
    }

    private function validateService(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'description' => ['nullable','string','max:2000'],
            'agreement_text' => ['nullable','string','max:20000'],
            'category' => ['nullable','string','max:255'],

            'price_type' => ['required', Rule::in(['fixed','range'])],
            'price_fixed' => ['nullable','numeric','min:0'],
            'price_from' => ['nullable','numeric','min:0'],
            'price_to' => ['nullable','numeric','min:0'],

            'duration_from_min' => ['nullable','integer','min:0','max:1440'],
            'duration_to_min' => ['nullable','integer','min:0','max:1440'],

            'buffer_before_min' => ['nullable','integer','min:0','max:1440'],
            'buffer_after_min' => ['nullable','integer','min:0','max:1440'],
        ]);

        if ($data['price_type'] === 'fixed') {
            if (!array_key_exists('price_fixed', $data) || $data['price_fixed'] === null) {
                abort(response()->json(['message' => 'price_fixed is required for fixed'], 422));
            }
            $data['price_from'] = null;
            $data['price_to'] = null;
        } else {
            if ($data['price_from'] === null || $data['price_to'] === null) {
                abort(response()->json(['message' => 'price_from and price_to are required for range'], 422));
            }
            if ($data['price_from'] > $data['price_to']) {
                abort(response()->json(['message' => 'price_from must be <= price_to'], 422));
            }
            $data['price_fixed'] = null;
        }

        if (isset($data['duration_from_min'], $data['duration_to_min']) && $data['duration_from_min'] > $data['duration_to_min']) {
            abort(response()->json(['message' => 'duration_from_min must be <= duration_to_min'], 422));
        }

        $data['buffer_before_min'] = (int)($data['buffer_before_min'] ?? 0);
        $data['buffer_after_min'] = (int)($data['buffer_after_min'] ?? 0);

        return $data;
    }
}
