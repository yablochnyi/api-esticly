<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MarketingAutomation;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingAutomationController extends Controller
{
    private function forbidStaffUser(Request $request): void
    {
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }
    }

    public function index(Request $request)
    {
        $this->forbidStaffUser($request);
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        $rows = MarketingAutomation::query()
            ->where('user_id', $orgId)
            ->orderBy('key')
            ->get(['key', 'enabled', 'delay_min']);

        $reviewLinkOpened = (int)(
            ShortLink::query()
                ->where('user_id', $orgId)
                ->where('key', 'review')
                ->value('click_count') ?? 0
        );

        $reviewsLeft = (int) DB::table('reviews')
            ->where('user_id', $orgId)
            ->where('source', 'sms_thanks_after_visit')
            ->count();

        return response()->json(
            $rows->map(fn ($r) => [
                'id' => (string)$r->key,
                'enabled' => (bool)$r->enabled,
                'delay_min' => (int)$r->delay_min,
                'stats' => [
                    'opened' => (string)$r->key === 'thanks_after_visit' ? $reviewLinkOpened : 0,
                    'reviews_left' => (string)$r->key === 'thanks_after_visit' ? $reviewsLeft : 0,
                ],
            ])->values()->all()
        );
    }

    public function upsert(Request $request, string $id)
    {
        $this->forbidStaffUser($request);
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        $id = trim($id);
        abort_if($id === '' || strlen($id) > 80, 404);
        abort_if(!preg_match('/^[a-z0-9_]+$/', $id), 404);

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'delay_min' => ['required', 'integer', 'min:0', 'max:525600'], // up to 365 days
        ]);

        MarketingAutomation::query()->updateOrCreate(
            ['user_id' => $orgId, 'key' => $id],
            [
                'enabled' => (bool)$data['enabled'],
                'delay_min' => (int)$data['delay_min'],
            ],
        );

        return response()->json(['ok' => true]);
    }
}
