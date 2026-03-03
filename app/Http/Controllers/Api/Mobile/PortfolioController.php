<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\PortfolioPhoto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PortfolioController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        // Ensure org exists (and timezone/etc. is consistent with other endpoints).
        User::query()->findOrFail($orgId);

        $limit = (int) $request->query('limit', 60);
        $limit = max(1, min($limit, 120));

        $cursor = $request->query('cursor');
        $cursor = $cursor === null ? null : (int) $cursor;

        $staffId = $request->filled('staff_id') ? (int) $request->query('staff_id') : null;

        $q = PortfolioPhoto::query()
            ->where('user_id', $orgId)
            ->when($staffId, fn($qq) => $qq->where('staff_id', $staffId))
            ->when($cursor, fn($qq) => $qq->where('id', '<', $cursor))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $q->count() > $limit;
        if ($hasMore) {
            $q = $q->take($limit)->values();
        }

        $items = $q->map(function ($p) {
            /** @var \App\Models\PortfolioPhoto $p */
            return [
                'id' => (int) $p->id,
                'url' => $p->url,
                'caption' => $p->caption,
                'created_at' => $p->created_at ? Carbon::parse($p->created_at)->toISOString() : null,
            ];
        })->values();

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $nextCursor = (int) $items->last()['id'];
        }

        // Count total photos for this org (useful for badges/UI).
        $total = PortfolioPhoto::query()
            ->where('user_id', $orgId)
            ->when($staffId, fn($qq) => $qq->where('staff_id', $staffId))
            ->count();

        return response()->json([
            'data' => $items,
            'next_cursor' => $nextCursor,
            'total' => (int) $total,
        ]);
    }

    public function store(Request $request)
    {
        // Only organization owner can manage portfolio.
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }

        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        User::query()->findOrFail($orgId);

        $isArray = is_array($request->file('photos'));
        $rules = [
            'caption' => ['nullable', 'string', 'max:255'],
        ];
        if ($isArray) {
            $rules['photos'] = ['required', 'array', 'min:1', 'max:20'];
            $rules['photos.*'] = ['required', 'image', 'max:5120'];
        } else {
            // allow single upload as well
            $rules['photos'] = ['required', 'image', 'max:5120'];
        }
        $data = $request->validate($rules);

        $files = $request->file('photos');
        if (!is_array($files)) $files = [$files];

        $created = [];
        foreach ($files as $f) {
            if (!$f) continue;
            $path = $f->store("portfolio/{$orgId}", 'public');
            $row = PortfolioPhoto::query()->create([
                'user_id' => $orgId,
                'staff_id' => null,
                'path' => $path,
                'caption' => $data['caption'] ?? null,
            ]);
            $created[] = [
                'id' => (int) $row->id,
                'url' => $row->url,
                'caption' => $row->caption,
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->toISOString() : null,
            ];
        }

        return response()->json(['data' => $created], 201);
    }

    public function destroy(Request $request, PortfolioPhoto $photo)
    {
        // Only organization owner can manage portfolio.
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }

        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;
        abort_unless((int)$photo->user_id === (int)$orgId, 404);

        if ($photo->path) {
            Storage::disk('public')->delete($photo->path);
        }
        $photo->delete();

        return response()->json(['ok' => true]);
    }
}

