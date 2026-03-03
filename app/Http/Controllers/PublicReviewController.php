<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ShortLink;
use App\Models\User;
use App\Support\PublicLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class PublicReviewController extends Controller
{
    private function orgBySlug(string $slug): User
    {
        $slug = trim($slug);
        abort_if($slug === '', 404);

        return User::query()
            ->where('booking_slug', $slug)
            ->firstOrFail();
    }

    public function show(Request $request, string $slug)
    {
        $org = $this->orgBySlug($slug);
        $lang = PublicLocale::resolve($request, (string)$org->language_code);
        App::setLocale($lang);
        $shortLinkCode = trim((string)$request->query('sl', ''));
        if ($shortLinkCode === '') {
            $shortLinkCode = null;
        }

        return view('review.form', [
            'org' => $org,
            'slug' => $slug,
            'short_link_code' => $shortLinkCode,
            'lang' => $lang,
        ]);
    }

    public function submit(Request $request, string $slug)
    {
        $org = $this->orgBySlug($slug);
        $lang = PublicLocale::resolve($request, (string)$org->language_code);
        App::setLocale($lang);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'text' => ['nullable', 'string', 'max:2000'],
            'sl' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
        ]);

        $shortLinkCode = isset($data['sl']) ? trim((string)$data['sl']) : '';
        $source = null;
        if ($shortLinkCode !== '') {
            $validShort = ShortLink::query()
                ->where('user_id', $org->id)
                ->where('key', 'review')
                ->where('code', $shortLinkCode)
                ->exists();
            if ($validShort) {
                $source = 'sms_thanks_after_visit';
            }
        }

        Review::query()->create([
            'user_id' => $org->id,
            'rating' => (int)$data['rating'],
            'text' => isset($data['text']) ? trim((string)$data['text']) : null,
            'source' => $source,
            'short_link_code' => $source ? $shortLinkCode : null,
        ]);

        return view('review.done', [
            'org' => $org,
            'lang' => $lang,
        ]);
    }
}
