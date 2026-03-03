<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicShortLinkController extends Controller
{
    public function go(Request $request, string $code)
    {
        $code = trim($code);
        abort_if($code === '' || strlen($code) > 32, 404);

        $link = ShortLink::query()
            ->where('code', $code)
            ->firstOrFail();

        $target = (string)($link->target_path ?? '');
        $target = '/' . ltrim($target, '/');

        // Safety: prevent open redirects.
        abort_if(str_contains($target, '://'), 404);
        abort_if(str_starts_with($target, '//'), 404);

        ShortLink::query()
            ->where('id', $link->id)
            ->update([
                'click_count' => DB::raw('click_count + 1'),
                'last_clicked_at' => now(),
            ]);

        if ((string)$link->key === 'review') {
            $sep = str_contains($target, '?') ? '&' : '?';
            $target .= $sep . 'sl=' . urlencode((string)$link->code);
        }

        return redirect($target, 302);
    }
}
