<!doctype html>
<html lang="{{ $lang ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>{{ $org->company_name ?? 'Review' }}</title>
    <style>
        :root{
            --bg:#F6F7FB;
            --card:#FFFFFF;
            --text:#111827;
            --muted:rgba(17,24,39,.55);
            --border:rgba(17,24,39,.10);
            --primary:#7C3AED;
            --radius:18px;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Arial,sans-serif;background:var(--bg);color:var(--text)}
        .wrap{max-width:540px;margin:0 auto;padding:16px 16px 40px}
        .card{background:rgba(255,255,255,.92);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
        h1{font-size:20px;margin:0 0 6px 0;font-weight:900}
        .muted{color:var(--muted)}
        .stars{display:flex;gap:6px;margin-top:12px}
        .star{
            width:44px;height:44px;border-radius:14px;
            border:1px solid var(--border);
            background:rgba(124,58,237,.06);
            display:flex;align-items:center;justify-content:center;
            font-size:22px;cursor:pointer;user-select:none;
            transition:transform .06s ease;
        }
        .star:active{transform:scale(.98)}
        .star.on{background:rgba(124,58,237,.18);border-color:rgba(124,58,237,.35)}
        textarea{
            width:100%;
            min-height:140px;
            margin-top:12px;
            border-radius:14px;
            border:1px solid var(--border);
            padding:12px;
            font-size:15px;
            resize:vertical;
            outline:none;
        }
        .btn{
            width:100%;
            height:54px;
            border-radius:999px;
            border:none;
            margin-top:12px;
            background:linear-gradient(135deg, rgba(124,58,237,.55), rgba(124,58,237,.30));
            color:var(--text);
            font-size:16px;
            font-weight:900;
            cursor:pointer;
        }
        .err{margin-top:10px;color:#B91C1C;font-weight:700}
        .row{display:flex;align-items:center;gap:10px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ $org->company_name ?? 'Salon' }}</h1>
        <div class="muted">{{ __('review.subtitle') }}</div>

        @if ($errors->any())
            <div class="err">
                @foreach ($errors->all() as $e)
                    <div>{{ $e }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('review.submit', ['slug' => $slug, 'lang' => ($lang ?? app()->getLocale())]) }}">
            @csrf
            <input type="hidden" name="rating" id="rating" value="{{ old('rating', 0) }}"/>
            <input type="hidden" name="sl" value="{{ old('sl', $short_link_code ?? '') }}"/>
            <input type="hidden" name="lang" value="{{ $lang ?? app()->getLocale() }}"/>

            <div class="stars" id="stars">
                @for ($i=1; $i<=5; $i++)
                    <div class="star" data-v="{{ $i }}">★</div>
                @endfor
            </div>

            <textarea name="text" maxlength="2000" placeholder="{{ __('review.placeholder') }}">{{ old('text') }}</textarea>

            <button class="btn" type="submit">{{ __('review.submit') }}</button>
        </form>
    </div>
</div>

<script>
    (function(){
        const ratingInput = document.getElementById('rating');
        const starsWrap = document.getElementById('stars');
        const stars = Array.from(starsWrap.querySelectorAll('.star'));

        function setRating(v){
            ratingInput.value = String(v);
            stars.forEach((s, idx) => {
                const on = (idx + 1) <= v;
                s.classList.toggle('on', on);
            });
        }

        stars.forEach((s) => {
            s.addEventListener('click', () => {
                const v = parseInt(s.getAttribute('data-v') || '0', 10);
                setRating(v);
            });
        });

        const initial = parseInt(ratingInput.value || '0', 10);
        if (initial > 0) setRating(initial);
    })();
</script>
</body>
</html>
