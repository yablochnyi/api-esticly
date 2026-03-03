<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $orgId = $u->organization_id ?? $u->id;

        $rows = DB::table('reviews')
            ->where('user_id', $orgId)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'rating', 'text', 'created_at']);

        return response()->json(
            $rows->map(fn ($r) => [
                'id' => (int)$r->id,
                'rating' => (int)$r->rating,
                'text' => (string)($r->text ?? ''),
                'created_at' => (string)$r->created_at,
            ])->all()
        );
    }
}

