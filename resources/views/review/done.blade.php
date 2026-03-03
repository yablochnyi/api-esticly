<!doctype html>
<html lang="{{ $lang ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>{{ $org->company_name ?? 'Review' }}</title>
    <style>
        :root{--bg:#F6F7FB;--text:#111827;--muted:rgba(17,24,39,.55);--border:rgba(17,24,39,.10);--radius:18px}
        *{box-sizing:border-box}
        body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Arial,sans-serif;background:var(--bg);color:var(--text)}
        .wrap{max-width:540px;margin:0 auto;padding:16px}
        .card{background:rgba(255,255,255,.92);border:1px solid var(--border);border-radius:var(--radius);padding:16px}
        h1{font-size:20px;margin:0 0 6px 0;font-weight:900}
        .muted{color:var(--muted)}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>{{ __('review.thanks_title') }}</h1>
        <div class="muted">{{ __('review.thanks_message', ['company' => ($org->company_name ?? __('review.salon_fallback'))]) }}</div>
    </div>
</div>
</body>
</html>
