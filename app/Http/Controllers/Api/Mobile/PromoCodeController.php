<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PromoCode;
use App\Models\Service;
use App\Models\User;
use App\Support\PromoCodes;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PromoCodeController extends Controller
{
    private function forbidStaff(Request $request): void
    {
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        return PromoCode::query()
            ->where('user_id', $orgId)
            ->orderByDesc('id')
            ->get(['code', 'type', 'amount', 'service_id', 'valid_until', 'active', 'created_at', 'updated_at']);
    }

    public function upsert(Request $request, string $code)
    {
        $this->forbidStaff($request);
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        $code = PromoCodes::norm($code);
        abort_if($code === '', 404);

        $data = $request->validate([
            'active' => ['required', 'boolean'],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'amount' => ['required', 'numeric', 'min:0'],
            'service_id' => ['nullable', 'integer'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (!empty($data['service_id'])) {
            Service::query()->where('user_id', $orgId)->where('id', (int)$data['service_id'])->firstOrFail();
        }

        $row = PromoCode::query()->updateOrCreate(
            ['user_id' => $orgId, 'code' => $code],
            [
                'active' => (bool)$data['active'],
                'type' => $data['type'],
                'amount' => (float)$data['amount'],
                'service_id' => !empty($data['service_id']) ? (int)$data['service_id'] : null,
                'valid_until' => !empty($data['valid_until']) ? $data['valid_until'] : null,
            ]
        );

        return response()->json($row);
    }

    public function destroy(Request $request, string $code)
    {
        $this->forbidStaff($request);
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        $code = PromoCodes::norm($code);
        abort_if($code === '', 404);

        PromoCode::query()
            ->where('user_id', $orgId)
            ->where('code', $code)
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'service_id' => ['required', 'integer'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'date_local' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        $org = User::query()->findOrFail($orgId);

        $service = Service::query()
            ->where('user_id', $orgId)
            ->where('id', (int)$data['service_id'])
            ->firstOrFail();

        $base = isset($data['price'])
            ? (float)$data['price']
            : (float)($service->price_fixed ?? $service->price_from ?? 0);

        $promo = PromoCodes::findActive($org, (string)$data['code']);
        $res = PromoCodes::apply(
            org: $org,
            promo: $promo,
            service: $service,
            basePrice: $base,
            localDateYmd: $data['date_local'] ?? null,
        );

        return response()->json([
            'ok' => (bool)$res['ok'],
            'message' => (string)$res['message'],
            'code' => PromoCodes::norm((string)$data['code']),
            'discount' => (float)$res['discount'],
            'final_price' => (float)$res['final'],
        ]);
    }
}

