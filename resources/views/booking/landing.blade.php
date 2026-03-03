@extends('booking.layout')

@php
    $title = $org->company_name ?? 'Booking';
@endphp

@section('body')
    <div class="wrap">
        <div style="height:6px"></div>
        <div class="card" style="padding:0;overflow:hidden">
            <div style="height:140px;background:linear-gradient(135deg, rgba(124,58,237,.22), rgba(124,58,237,.06));"></div>
            <div style="padding:16px;text-align:center">
                <div style="font-size:22px;font-weight:900">{{ $org->company_name ?? '—' }}</div>
                <div style="height:10px"></div>
                <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                    <div class="pill"><span style="color:var(--primary);font-weight:900">0.0</span><span class="muted">{{ __('booking.rating') }}</span></div>
                    @if(!empty($org->phone))
                        <a class="pill" href="tel:{{ $org->phone }}" style="text-decoration:none;color:inherit">
                            <span style="color:var(--primary);font-weight:900">☎</span>
                            <span style="font-weight:900">{{ $org->phone }}</span>
                        </a>
                    @else
                        <div class="pill"><span style="color:var(--primary);font-weight:900">☎</span><span class="muted">{{ __('booking.phone_not_set') }}</span></div>
                    @endif

                    @if(!empty($org->address))
                        <a class="pill"
                           href="https://www.google.com/maps/search/?api=1&query={{ urlencode($org->address) }}"
                           target="_blank" rel="noopener"
                           style="text-decoration:none;color:inherit">
                            <span style="color:var(--primary);font-weight:900">⌁</span>
                            <span style="font-weight:900">{{ $org->address }}</span>
                        </a>
                    @else
                        <div class="pill"><span style="color:var(--primary);font-weight:900">⌁</span><span class="muted">{{ __('booking.address_not_set') }}</span></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="tabs">
            <a class="tab active" href="#about">{{ __('booking.tabs.about') }}</a>
            <a class="tab" href="#portfolio">{{ __('booking.tabs.portfolio') }}</a>
            <a class="tab" href="#services">{{ __('booking.tabs.services') }}</a>
        </div>

        <div id="about" style="margin-top:14px">
            <div class="sectionTitle">{{ __('booking.about_title') }}</div>
            <div class="card">
                <div>{{ $org->description ?: '—' }}</div>
            </div>

            <div class="sectionTitle">{{ __('booking.schedule_title') }}</div>
            <div class="card">
                <div class="scheduleRow">
                    @foreach($scheduleRows as $row)
                        <div class="scheduleCell purple">{{ $row['label'] }}:</div>
                        <div class="scheduleCell right">{{ $row['enabled'] ? ($row['start'].' - '.$row['end']) : __('booking.day_off') }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        <div id="portfolio" style="margin-top:18px">
            <div class="sectionTitle">{{ __('booking.portfolio_title') }}</div>
            @if(!empty($portfolioPhotos) && count($portfolioPhotos) > 0)
                <style>
                    .lbOverlay{
                        position:fixed;inset:0;z-index:50;
                        background:rgba(17,24,39,.72);
                        display:none;align-items:center;justify-content:center;
                        padding:18px;
                    }
                    .lbCard{
                        width:min(920px,100%);
                        max-height:calc(100vh - 36px);
                        position:relative;
                    }
                    .lbImg{
                        width:100%;
                        height:auto;
                        max-height:calc(100vh - 110px);
                        object-fit:contain;
                        display:block;
                        border-radius:16px;
                        border:1px solid rgba(255,255,255,.20);
                        background:rgba(255,255,255,.08);
                        backdrop-filter: blur(6px);
                    }
                    .lbClose{
                        position:absolute;right:10px;top:10px;
                        width:40px;height:40px;border-radius:999px;
                        border:1px solid rgba(255,255,255,.25);
                        background:rgba(255,255,255,.14);
                        color:#fff;font-size:22px;font-weight:900;
                        display:flex;align-items:center;justify-content:center;
                        cursor:pointer;
                    }
                </style>

                <div class="card" style="padding:12px">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
                        @foreach($portfolioPhotos as $p)
                            @if(!empty($p->url))
                                <a href="{{ $p->url }}" data-lightbox="1" style="display:block">
                                    <img
                                        src="{{ $p->url }}"
                                        alt="{{ $p->caption ?? __('booking.portfolio_title') }}"
                                        loading="lazy"
                                        style="width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:14px;border:1px solid var(--border);display:block"
                                    />
                                </a>
                            @endif
                        @endforeach
                    </div>
                    @php($totalShown = count($portfolioPhotos))
                    @if($totalShown >= 30)
                        <div class="muted" style="margin-top:10px;font-size:12px">{{ __('booking.photos_last_shown', ['count' => $totalShown]) }}</div>
                    @endif
                </div>
            @else
                <div class="card">
                    <div class="muted">{{ __('booking.portfolio_empty') }}</div>
                </div>
            @endif
        </div>

        @if(!empty($portfolioPhotos) && count($portfolioPhotos) > 0)
            <div id="lb" class="lbOverlay" role="dialog" aria-modal="true" aria-label="Portfolio photo">
                <div class="lbCard">
                    <button type="button" id="lbClose" class="lbClose" aria-label="Close">×</button>
                    <img id="lbImg" class="lbImg" alt="Portfolio"/>
                </div>
            </div>
        @endif

        <div id="services" style="margin-top:18px">
            <div class="sectionTitle">{{ __('booking.services_title') }}</div>
            <div class="services grid">
                @if(!empty($serviceCards) && count($serviceCards) > 0)
                @foreach($serviceCards as $s)
                    <div class="item">
                        <div>
                            <div class="name">{{ $s['name'] }}</div>
                            <div class="meta">{{ $s['duration'] }} {{ __('booking.minutes_short') }}</div>
                        </div>
                        <div class="price">
                            {{ $s['price'] }} {{ $s['currency'] }}
                        </div>
                    </div>
                @endforeach
                @else
                    <div class="card muted">{{ __('booking.services_empty') }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="fixedBottom">
        <div style="max-width:540px;margin:0 auto">
            <a class="btn btn-primary" href="{{ route('booking.book', ['slug' => $org->booking_slug, 'lang' => ($lang ?? app()->getLocale())]) }}">{{ __('booking.book_now') }}</a>
        </div>
    </div>

    <script>
        // Tabs: show only one section at a time (no mixed page)
        const tabs = Array.from(document.querySelectorAll('.tab'));
        const sections = ['about', 'portfolio', 'services'];

        function showOnly(id) {
            sections.forEach(s => {
                const el = document.getElementById(s);
                if (!el) return;
                el.style.display = (s === id) ? 'block' : 'none';
            });
        }

        function setActive(hash) {
            const h = (hash && hash.startsWith('#')) ? hash : '#about';
            const id = h.substring(1);
            if (!sections.includes(id)) return setActive('#about');
            tabs.forEach(t => t.classList.toggle('active', t.getAttribute('href') === '#' + id));
            showOnly(id);
        }

        tabs.forEach(t => t.addEventListener('click', (e) => {
            e.preventDefault();
            const hash = t.getAttribute('href') || '#about';
            history.replaceState(null, '', hash);
            setActive(hash);
        }));

        window.addEventListener('hashchange', () => setActive(location.hash || '#about'));
        setActive(location.hash || '#about');

        // Lightbox for portfolio (open in same window)
        const lb = document.getElementById('lb');
        const lbImg = document.getElementById('lbImg');
        const lbClose = document.getElementById('lbClose');
        function lbHide(){
            if (!lb) return;
            lb.style.display = 'none';
            document.body.style.overflow = '';
            if (lbImg) lbImg.src = '';
        }
        function lbShow(url){
            if (!lb || !lbImg) return;
            lbImg.src = url;
            lb.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        document.querySelectorAll('a[data-lightbox="1"]').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const url = a.getAttribute('href');
                if (url) lbShow(url);
            });
        });
        if (lbClose) lbClose.addEventListener('click', lbHide);
        if (lb) lb.addEventListener('click', (e) => {
            if (e.target === lb) lbHide();
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') lbHide();
        });
    </script>
@endsection

