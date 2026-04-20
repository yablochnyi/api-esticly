<!doctype html>
<html lang="{{ $lang ?? app()->getLocale() }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <title>{{ $title ?? ($org->company_name ?? 'Booking') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/css/intlTelInput.css">
    <link rel="stylesheet" href="{{ asset('assets/booking/src/style.css') }}">
    <style>
        :root{
            --bg:#F6F7FB;
            --card:#FFFFFF;
            --text:#111827;
            --muted:rgba(17,24,39,.55);
            --border:rgba(17,24,39,.10);
            --primary:#7C3AED;
            --primary-10:rgba(124,58,237,.10);
            --primary-18:rgba(124,58,237,.18);
            --radius:18px;
        }
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Arial,sans-serif;
            background:var(--bg);
            color:var(--text);
        }
        .wrap{max-width:540px;margin:0 auto;padding:16px 16px 110px}
        .topbar{display:flex;align-items:center;gap:10px;margin-top:6px}
        .back{
            width:38px;height:38px;border-radius:999px;
            border:1px solid var(--border);
            background:rgba(255,255,255,.75);
            display:flex;align-items:center;justify-content:center;
            text-decoration:none;color:var(--text);
        }
        .title{font-size:20px;font-weight:800;margin:0}
        .card{
            background:rgba(255,255,255,.90);
            border:1px solid var(--border);
            border-radius:var(--radius);
            padding:16px;
        }
        .muted{color:var(--muted)}
        .btn{
            width:100%;
            height:54px;
            border-radius:999px;
            border:none;
            background:linear-gradient(135deg, rgba(124,58,237,.35), rgba(124,58,237,.20));
            color:var(--text);
            font-size:16px;
            font-weight:800;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
            text-decoration:none;
        }
        .btn-primary{
            background:linear-gradient(135deg, rgba(124,58,237,.55), rgba(124,58,237,.30));
        }
        .pill{
            display:inline-flex;align-items:center;gap:8px;
            padding:10px 12px;border-radius:14px;border:1px solid var(--border);
            background:rgba(255,255,255,.75);
        }
        .tabs{display:flex;gap:18px;align-items:center;margin-top:14px;border-bottom:1px solid var(--border)}
        .tab{
            padding:12px 0;
            font-weight:700;
            color:rgba(17,24,39,.55);
            text-decoration:none;
            border-bottom:2px solid transparent;
        }
        .tab.active{color:var(--primary);border-bottom-color:var(--primary)}
        .fixedBottom{
            position:fixed;left:0;right:0;bottom:0;
            padding:12px 16px 18px;
            background:linear-gradient(180deg, rgba(246,247,251,0) 0%, rgba(246,247,251,.85) 30%, rgba(246,247,251,1) 100%);
        }
        .banner{
            position:sticky;top:0;z-index:5;
            margin:-16px -16px 16px;
            padding:10px 16px;
            background:rgba(255,255,255,.85);
            border-bottom:1px solid var(--border);
            backdrop-filter:saturate(140%) blur(10px);
        }
        .banner .row{display:flex;align-items:center;gap:12px}
        .logoMark{
            width:34px;height:34px;border-radius:12px;
            background:var(--primary-10);
            border:1px solid var(--border);
            display:flex;align-items:center;justify-content:center;
            color:var(--primary);font-weight:900;
        }
        .banner .h{font-weight:800}
        .banner .s{font-size:12px;color:var(--muted)}
        .grid{display:grid;gap:10px}
        .services .item{
            display:flex;justify-content:space-between;gap:10px;
            padding:14px;border:1px solid var(--border);
            border-radius:16px;background:rgba(255,255,255,.85);
        }
        .services .name{font-weight:800}
        .services .meta{font-size:12px;color:var(--muted);margin-top:2px}
        .services .price{font-weight:900}
        .scheduleRow{
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap:10px;
            margin-top:10px;
        }
        .scheduleCell{
            padding:12px 14px;border-radius:14px;border:1px solid var(--border);
            background:rgba(255,255,255,.85);
            font-weight:700;
        }
        .scheduleCell.right{display:flex;justify-content:center}
        .scheduleCell.purple{background:rgba(124,58,237,.14);border-color:rgba(124,58,237,.18)}
        .sectionTitle{font-size:14px;font-weight:900;margin:14px 0 8px}
        /* intl-tel-input */
        .iti{width:100%}
    </style>
</head>
<body>
@yield('body')
<script defer src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/js/intlTelInput.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/js/utils.js"></script>
</body>
</html>
