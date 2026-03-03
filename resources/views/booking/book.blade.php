@extends('booking.layout')

@php
    $title = __('booking.create_title');
@endphp

@section('body')
    <div class="wrap">
        <div style="height:6px"></div>
        <h1 class="title" style="margin:0 0 10px">{{ __('booking.create_title') }}</h1>

        <div class="card" style="padding:14px">
            <div style="display:flex;justify-content:space-between;align-items:flex-end">
                <div>
                    <div class="muted" style="font-weight:700">{{ __('booking.step') }} <span id="stepNo">1</span>/4</div>
                    <div style="font-size:28px;font-weight:900;margin-top:4px" id="stepTitle">{{ __('booking.steps.service') }}</div>
                </div>
            </div>

            <div style="height:10px"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;align-items:end">
                <div style="height:3px;border-radius:999px;background:var(--primary)" id="bar1"></div>
                <div style="height:3px;border-radius:999px;background:rgba(17,24,39,.12)" id="bar2"></div>
                <div style="height:3px;border-radius:999px;background:rgba(17,24,39,.12)" id="bar3"></div>
                <div style="height:3px;border-radius:999px;background:rgba(17,24,39,.12)" id="bar4"></div>
            </div>

            <div style="height:10px"></div>
            <div style="display:flex;gap:18px;font-weight:700;color:rgba(17,24,39,.55)">
                <div id="t1" style="color:var(--primary)">{{ __('booking.steps.service') }}</div>
                <div id="t2">{{ __('booking.steps.staff') }}</div>
                <div id="t3">{{ __('booking.steps.datetime') }}</div>
                <div id="t4">{{ __('booking.steps.details') }}</div>
            </div>
        </div>

        <div style="height:14px"></div>

        <div id="step1" class="card">
            <div class="muted" style="font-weight:700;margin-bottom:10px">{{ __('booking.choose_service') }}</div>
            <div class="services grid">
                @foreach($services as $s)
                    @php
                        $code = $org->currency_code ?: '';
                        if (($s->price_type ?? 'fixed') === 'range') {
                            $p = ($s->price_from ?? '—') . '–' . ($s->price_to ?? '—');
                        } else {
                            $p = $s->price_fixed ?? '—';
                        }
                        $dur = ($s->duration_from_min ?? $s->duration_to_min ?? 0);
                    @endphp
                    <label class="item" style="cursor:pointer;align-items:center">
                        <div>
                            <div class="name">{{ $s->name }}</div>
                            <div class="meta">{{ $dur }} {{ __('booking.minutes_short') }}</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div class="price">{{ $p }} {{ $code }}</div>
                            <input type="radio" name="service_id" value="{{ $s->id }}" @checked($selectedServiceId===$s->id) />
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div id="step2" class="card" style="display:none">
            <div class="muted" style="font-weight:700;margin-bottom:10px">{{ __('booking.steps.staff') }}</div>
            <div class="pill" style="width:100%;justify-content:space-between">
                <div style="font-weight:800">{{ $org->company_name ?? '—' }}</div>
                <div class="muted">{{ $tz }}</div>
            </div>
            <div style="height:12px"></div>
            <div id="staffList" style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px"></div>
            <div id="staffEmpty" class="muted" style="margin-top:10px;display:none">{{ __('booking.staff_empty') }}</div>
        </div>

        <div id="step3" class="card" style="display:none">
            <div class="muted" style="font-weight:700;margin-bottom:10px">{{ __('booking.steps.datetime') }}</div>
            <div class="card" style="background:rgba(17,24,39,.03);border-radius:16px;border:1px solid var(--border);padding:14px">
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <div style="font-weight:800" id="monthLabel">—</div>
                    <div style="display:flex;gap:10px">
                        <button type="button" id="prevMonth" class="back">‹</button>
                        <button type="button" id="nextMonth" class="back">›</button>
                    </div>
                </div>
                <div style="height:12px"></div>
                <div id="cal" style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px"></div>
            </div>
            <div style="height:12px"></div>
            <div id="times" style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px"></div>
            <div id="timesEmpty" class="muted" style="margin-top:10px;display:none">{{ __('booking.no_free_time') }}</div>
        </div>

        <div id="step4" class="card" style="display:none">
            <form method="POST" action="{{ route('booking.submit', ['slug' => $org->booking_slug, 'lang' => ($lang ?? app()->getLocale())]) }}">
                @csrf
                <input type="hidden" name="lang" value="{{ $lang ?? app()->getLocale() }}">
                <input type="hidden" name="service_id" id="f_service_id" value="">
                <input type="hidden" name="staff_id" id="f_staff_id" value="">
                <input type="hidden" name="date" id="f_date" value="">
                <input type="hidden" name="time" id="f_time" value="">
                <input type="hidden" name="promo_code" id="f_promo_code" value="">

                <div class="muted" style="font-weight:700;margin-bottom:10px">{{ __('booking.steps.details') }}</div>

                <div class="card" style="background:rgba(17,24,39,.03);border-radius:16px;border:1px solid var(--border);padding:14px">
                    <div style="display:flex;justify-content:space-between;gap:10px">
                        <div>
                            <div style="font-weight:900" id="sumService">—</div>
                            <div class="muted" id="sumDate">—</div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-weight:900" id="sumPrice">—</div>
                            <div class="muted" id="sumPromo" style="display:none"></div>
                        </div>
                    </div>
                </div>

                <div style="height:12px"></div>

                <div class="card" style="background:rgba(17,24,39,.03);border-radius:16px;border:1px solid var(--border);padding:14px">
                    <label style="display:block;font-weight:800;margin-bottom:6px">{{ __('booking.promo_code') }}</label>
                    <div style="display:flex;gap:10px;align-items:center">
                        <input id="promoInput" style="flex:1;height:48px;border-radius:14px;border:1px solid var(--border);padding:0 12px;font-size:16px;text-transform:uppercase" placeholder="PROMO2026">
                        <button id="promoApply" type="button" class="btn" style="height:48px;white-space:nowrap">{{ __('booking.apply') }}</button>
                    </div>
                    <div id="promoMsg" class="muted" style="margin-top:8px;display:none"></div>
                </div>

                <div style="height:12px"></div>

                <div class="card" style="background:rgba(17,24,39,.03);border-radius:16px;border:1px solid var(--border);padding:14px">
                    <label style="display:block;font-weight:800;margin-bottom:6px">{{ __('booking.name') }}</label>
                    <input name="name" required style="width:100%;height:48px;border-radius:14px;border:1px solid var(--border);padding:0 12px;font-size:16px" value="{{ old('name') }}">
                    <div style="height:10px"></div>
                    <label style="display:block;font-weight:800;margin-bottom:6px">{{ __('booking.phone') }}</label>
                    <input id="phoneInput" name="phone" required style="width:100%;height:48px;border-radius:14px;border:1px solid var(--border);padding:0 12px;font-size:16px" value="{{ old('phone') }}">
                    <div style="height:10px"></div>
                    <label style="display:block;font-weight:800;margin-bottom:6px">{{ __('booking.comment') }}</label>
                    <textarea name="comment" rows="3" style="width:100%;border-radius:14px;border:1px solid var(--border);padding:10px 12px;font-size:16px">{{ old('comment') }}</textarea>
                </div>

                <div style="height:14px"></div>
                <button type="submit" class="btn btn-primary">{{ __('booking.submit_booking') }} ✓</button>
            </form>
        </div>
    </div>

    <div class="fixedBottom">
        <div style="max-width:540px;margin:0 auto">
            <button id="nextBtn" class="btn" type="button" disabled>{{ __('booking.continue') }} →</button>
        </div>
    </div>

    @php
        $servicesForJs = $services->map(function ($s) {
            return [
                'id' => (int)($s->id ?? 0),
                'name' => (string)($s->name ?? ''),
                'price_type' => $s->price_type ?? 'fixed',
                'price_fixed' => $s->price_fixed,
                'price_from' => $s->price_from,
                'price_to' => $s->price_to,
                'duration' => (int)($s->duration_from_min ?? $s->duration_to_min ?? 0),
            ];
        })->values();
    @endphp

    <script>
        const PAGE_LANG = @json($lang ?? app()->getLocale());
        const I18N = {
            chooseService: @json(__('booking.choose_service')),
            promoApplied: @json(__('booking.promo_applied')),
            promoInactive: @json(__('booking.promo_inactive')),
            promoCheckError: @json(__('booking.promo_check_error')),
            steps: {
                service: @json(__('booking.steps.service')),
                staff: @json(__('booking.steps.staff')),
                datetime: @json(__('booking.steps.datetime')),
                details: @json(__('booking.steps.details')),
            },
            salon: @json(__('booking.salon')),
            dayShort: [
                @json(__('booking.days_short.mon')),
                @json(__('booking.days_short.tue')),
                @json(__('booking.days_short.wed')),
                @json(__('booking.days_short.thu')),
                @json(__('booking.days_short.fri')),
                @json(__('booking.days_short.sat')),
                @json(__('booking.days_short.sun')),
            ],
        };
        const services = @json($servicesForJs);

        const state = {
            step: 1,
            serviceId: {{ $selectedServiceId ?? 'null' }},
            staffId: null,
            date: null,
            time: null,
            promo: null,
            promoDiscount: null,
            promoFinalPrice: null,
        };

        const stepTitle = document.getElementById('stepTitle');
        const stepNo = document.getElementById('stepNo');
        const nextBtn = document.getElementById('nextBtn');
        const steps = [null, document.getElementById('step1'), document.getElementById('step2'), document.getElementById('step3'), document.getElementById('step4')];
        const sumPromo = document.getElementById('sumPromo');
        const promoInput = document.getElementById('promoInput');
        const promoApply = document.getElementById('promoApply');
        const promoMsg = document.getElementById('promoMsg');

        function promoStatus(text, ok){
            promoMsg.style.display = 'block';
            promoMsg.textContent = text;
            promoMsg.style.color = ok ? 'rgba(16,185,129,1)' : 'rgba(239,68,68,1)';
        }

        function basePriceValue(svc){
            if (!svc) return 0;
            if (svc.price_type === 'range') return Number(svc.price_from ?? 0);
            return Number(svc.price_fixed ?? 0);
        }

        function renderSummary(){
            const svc = services.find(s=>s.id===state.serviceId);
            document.getElementById('sumService').textContent = svc ? svc.name : '—';
            document.getElementById('sumDate').textContent = (state.date && state.time) ? (state.date + ' ' + state.time) : '—';

            const base = basePriceValue(svc);
            const finalP = (state.promoFinalPrice != null) ? state.promoFinalPrice : base;
            const code = '{{ $org->currency_code ?: '' }}';
            const priceText = (svc && svc.price_type === 'range' && state.promoFinalPrice == null)
                ? ((svc.price_from ?? '—') + '–' + (svc.price_to ?? '—'))
                : (String(finalP));
            document.getElementById('sumPrice').textContent = (priceText + ' ' + code).trim();

            if (state.promo && state.promoDiscount != null && Number(state.promoDiscount) > 0) {
                sumPromo.style.display = 'block';
                sumPromo.textContent = `-${state.promoDiscount} (${state.promo})`;
            } else {
                sumPromo.style.display = 'none';
            }
        }

        async function applyPromo(){
            const code = (promoInput.value || '').trim().toUpperCase();
            state.promo = null;
            state.promoDiscount = null;
            state.promoFinalPrice = null;
            document.getElementById('f_promo_code').value = '';
            sumPromo.style.display = 'none';

            if (!code) {
                promoMsg.style.display = 'none';
                renderSummary();
                return;
            }
            if (!state.serviceId) {
                promoStatus(I18N.chooseService, false);
                return;
            }
            try{
                const q = new URLSearchParams({service_id: String(state.serviceId), code, date: state.date || '', lang: PAGE_LANG});
                const res = await fetch(`{{ route('booking.promo.validate', ['slug' => $org->booking_slug]) }}?` + q.toString(), { headers: { 'Accept': 'application/json' }});
                const json = await res.json();
                if (json && json.ok) {
                    state.promo = json.code;
                    state.promoFinalPrice = json.final_price;
                    state.promoDiscount = json.discount;
                    document.getElementById('f_promo_code').value = json.code;
                    promoStatus(I18N.promoApplied, true);
                } else {
                    promoStatus(I18N.promoInactive, false);
                }
            } catch(e){
                promoStatus(I18N.promoCheckError, false);
            }
            renderSummary();
        }

        promoApply.addEventListener('click', applyPromo);
        promoInput.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); applyPromo(); } });

        function setStep(n){
            state.step = n;
            steps.forEach((el, i)=>{ if(!el) return; el.style.display = (i===n ? 'block' : 'none'); });
            const titles = {1:I18N.steps.service,2:I18N.steps.staff,3:I18N.steps.datetime,4:I18N.steps.details};
            stepTitle.textContent = titles[n];
            stepNo.textContent = n;
            document.getElementById('bar1').style.background = n>=1?'var(--primary)':'rgba(17,24,39,.12)';
            document.getElementById('bar2').style.background = n>=2?'var(--primary)':'rgba(17,24,39,.12)';
            document.getElementById('bar3').style.background = n>=3?'var(--primary)':'rgba(17,24,39,.12)';
            document.getElementById('bar4').style.background = n>=4?'var(--primary)':'rgba(17,24,39,.12)';
            document.getElementById('t1').style.color = n===1?'var(--primary)':'rgba(17,24,39,.55)';
            document.getElementById('t2').style.color = n===2?'var(--primary)':'rgba(17,24,39,.55)';
            document.getElementById('t3').style.color = n===3?'var(--primary)':'rgba(17,24,39,.55)';
            document.getElementById('t4').style.color = n===4?'var(--primary)':'rgba(17,24,39,.55)';

            // next button
            if (n === 4) {
                nextBtn.style.display = 'none';
                renderSummary();
            } else {
                nextBtn.style.display = 'flex';
            }
            updateNextEnabled();
        }

        function updateNextEnabled(){
            let ok = false;
            if (state.step === 1) ok = !!state.serviceId;
            if (state.step === 2) ok = (state.staffId !== null);
            if (state.step === 3) ok = !!state.date && !!state.time && (state.staffId !== null);
            nextBtn.disabled = !ok;
        }

        // service select
        document.querySelectorAll('input[name="service_id"]').forEach(r => {
            r.addEventListener('change', () => {
                state.serviceId = parseInt(r.value, 10);
                state.staffId = null;
                state.date = null;
                state.time = null;
                loadStaff();
                updateNextEnabled();
            });
        });

        const staffList = document.getElementById('staffList');
        const staffEmpty = document.getElementById('staffEmpty');
        let staffCache = [];

        function renderStaff(list){
            staffCache = Array.isArray(list) ? list : [];
            staffList.innerHTML = '';
            staffEmpty.style.display = 'none';

            // "Salon" option (no master) => staff_id=0
            const salonBtn = document.createElement('button');
            salonBtn.type = 'button';
            salonBtn.textContent = I18N.salon;
            salonBtn.style.height = '48px';
            salonBtn.style.borderRadius = '16px';
            salonBtn.style.border = '1px solid rgba(17,24,39,.18)';
            const salonSelected = state.staffId === 0;
            salonBtn.style.background = salonSelected ? 'rgba(124,58,237,.18)' : 'rgba(255,255,255,.85)';
            salonBtn.style.fontWeight = salonSelected ? '900' : '700';
            salonBtn.addEventListener('click', async () => {
                state.staffId = 0;
                state.date = null;
                state.time = null;
                await loadAvailability();
                renderStaff(staffCache);
                updateNextEnabled();
            });
            staffList.appendChild(salonBtn);

            staffCache.forEach(s => {
                const b = document.createElement('button');
                b.type = 'button';
                b.textContent = s.name;
                b.style.height = '48px';
                b.style.borderRadius = '16px';
                b.style.border = '1px solid rgba(17,24,39,.18)';
                const selected = state.staffId === s.id;
                b.style.background = selected ? 'rgba(124,58,237,.18)' : 'rgba(255,255,255,.85)';
                b.style.fontWeight = selected ? '900' : '700';
                b.addEventListener('click', async () => {
                    state.staffId = s.id;
                    state.date = null;
                    state.time = null;
                    await loadAvailability();
                    renderStaff(staffCache);
                    updateNextEnabled();
                });
                staffList.appendChild(b);
            });
        }

        async function loadStaff(dateYmd=null){
            renderStaff([]);
            if (!state.serviceId) return;
            const url = '{{ route('booking.staff', ['slug' => $org->booking_slug]) }}'
                + '?service_id=' + encodeURIComponent(state.serviceId)
                + (dateYmd ? ('&date=' + encodeURIComponent(dateYmd)) : '')
                + '&lang=' + encodeURIComponent(PAGE_LANG);
            const res = await fetch(url);
            const data = await res.json();
            renderStaff(data.data || []);
        }

        // month availability -> disabled days
        let availableDates = new Set();
        async function loadAvailability(){
            availableDates = new Set();
            if (!state.serviceId || state.staffId === null) {
                renderCalendar();
                return;
            }
            const month = view.getFullYear() + '-' + pad2(view.getMonth() + 1);
            const url = '{{ route('booking.availability', ['slug' => $org->booking_slug]) }}'
                + '?service_id=' + encodeURIComponent(state.serviceId)
                + '&month=' + encodeURIComponent(month)
                + '&staff_id=' + encodeURIComponent(state.staffId)
                + '&lang=' + encodeURIComponent(PAGE_LANG);
            const res = await fetch(url);
            const data = await res.json();
            const list = data.available_dates || [];
            availableDates = new Set(list);
            renderCalendar();
        }

        // calendar
        const cal = document.getElementById('cal');
        const monthLabel = document.getElementById('monthLabel');
        const times = document.getElementById('times');
        const timesEmpty = document.getElementById('timesEmpty');

        let view = new Date();
        function jsLocaleFrom(lang) {
            const map = {
                uk: 'uk-UA',
                pl: 'pl-PL',
                en: 'en-US',
                it: 'it-IT',
                fr: 'fr-FR',
                pt: 'pt-PT',
                de: 'de-DE',
                es: 'es-ES',
                cs: 'cs-CZ',
            };
            return map[lang] || 'en-US';
        }
        function fmtMonth(d){
            return d.toLocaleString(jsLocaleFrom(PAGE_LANG), { month:'long', year:'numeric' });
        }
        function pad2(n){ return String(n).padStart(2,'0'); }
        function ymd(d){ return d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate()); }

        function renderCalendar(){
            cal.innerHTML = '';
            monthLabel.textContent = fmtMonth(view);
            const first = new Date(view.getFullYear(), view.getMonth(), 1);
            const last = new Date(view.getFullYear(), view.getMonth()+1, 0);
            const today = new Date();
            const todayYmd = ymd(today);
            const startDow = (first.getDay() + 6) % 7; // monday=0
            const total = startDow + last.getDate();
            const rows = Math.ceil(total / 7);
            const dayNames = I18N.dayShort;
            dayNames.forEach(n=>{
                const el = document.createElement('div');
                el.textContent = n;
                el.style.fontWeight = '800';
                el.style.color = 'rgba(17,24,39,.55)';
                el.style.textAlign = 'center';
                el.style.padding = '6px 0';
                cal.appendChild(el);
            });
            for(let i=0;i<startDow;i++){
                const el = document.createElement('div'); el.textContent=''; cal.appendChild(el);
            }
            for(let d=1; d<=last.getDate(); d++){
                const date = new Date(view.getFullYear(), view.getMonth(), d);
                const dateYmd = ymd(date);
                const el = document.createElement('button');
                el.type='button';
                el.textContent = pad2(d);
                el.style.height='40px';
                el.style.borderRadius='14px';
                el.style.border='1px solid rgba(17,24,39,.10)';
                const isSelected = (state.date === dateYmd);
                const isPast = dateYmd < todayYmd;
                const hasAvail = availableDates.size ? availableDates.has(dateYmd) : true;
                const disabled = isPast || !hasAvail || state.staffId === null;
                el.disabled = disabled;
                el.style.background = isSelected ? 'rgba(124,58,237,.20)' : 'rgba(255,255,255,.85)';
                el.style.fontWeight = isSelected ? '900':'700';
                el.style.opacity = disabled ? '0.35' : '1';
                el.addEventListener('click', async ()=>{
                    if (el.disabled) return;
                    state.date = dateYmd;
                    state.time = null;
                    renderCalendar();
                    await loadTimes();
                    updateNextEnabled();
                });
                cal.appendChild(el);
            }
        }

        async function loadTimes(){
            times.innerHTML = '';
            timesEmpty.style.display = 'none';
            if (!state.date || !state.serviceId || state.staffId === null) return;
            const url = '{{ route('booking.slots', ['slug' => $org->booking_slug]) }}'
                + '?date=' + encodeURIComponent(state.date)
                + '&service_id=' + encodeURIComponent(state.serviceId)
                + '&staff_id=' + encodeURIComponent(state.staffId)
                + '&lang=' + encodeURIComponent(PAGE_LANG);
            const res = await fetch(url);
            const data = await res.json();
            const list = data.times || [];
            if (!list.length){
                timesEmpty.style.display = 'block';
                return;
            }
            list.forEach(t=>{
                const b = document.createElement('button');
                b.type='button';
                b.textContent = t;
                b.style.height='42px';
                b.style.borderRadius='16px';
                b.style.border='1px solid rgba(17,24,39,.18)';
                b.style.background = (state.time===t) ? 'rgba(124,58,237,.18)' : 'rgba(255,255,255,.85)';
                b.style.fontWeight = (state.time===t) ? '900' : '700';
                b.addEventListener('click', ()=>{
                    state.time = t;
                    loadTimes();
                    updateNextEnabled();
                });
                times.appendChild(b);
            });
        }

        document.getElementById('prevMonth').addEventListener('click', ()=>{
            view = new Date(view.getFullYear(), view.getMonth()-1, 1);
            loadAvailability();
        });
        document.getElementById('nextMonth').addEventListener('click', ()=>{
            view = new Date(view.getFullYear(), view.getMonth()+1, 1);
            loadAvailability();
        });

        nextBtn.addEventListener('click', ()=>{
            if (state.step === 1) setStep(2);
            else if (state.step === 2) setStep(3);
            else if (state.step === 3) {
                // fill form
                document.getElementById('f_service_id').value = state.serviceId;
                document.getElementById('f_staff_id').value = state.staffId;
                document.getElementById('f_date').value = state.date;
                document.getElementById('f_time').value = state.time;
                setStep(4);
                // init phone mask after step 4 becomes visible
                setTimeout(initPhoneMask, 0);
            }
        });

        // phone mask (country picker)
        let iti = null;
        function initPhoneMask() {
            const phoneInput = document.getElementById('phoneInput');
            if (!phoneInput) return;
            if (phoneInput.dataset.itiInit === '1') return;
            if (!window.intlTelInput) return;
            phoneInput.dataset.itiInit = '1';
            iti = window.intlTelInput(phoneInput, {
                initialCountry: 'ua',
                preferredCountries: ['ua', 'pl', 'de', 'gb', 'us'],
                separateDialCode: true,
                autoPlaceholder: 'aggressive',
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/js/utils.js',
            });
            const form = phoneInput.closest('form');
            if (form) {
                form.addEventListener('submit', () => {
                    if (iti) {
                        const v = iti.getNumber();
                        if (v) phoneInput.value = v;
                    }
                    phoneInput.value = (phoneInput.value || '').trim();
                });
            }
        }
        document.addEventListener('DOMContentLoaded', initPhoneMask);

        // init
        if (state.serviceId) {
            // mark radio checked if preselected
            const r = document.querySelector('input[name="service_id"][value="'+state.serviceId+'"]');
            if (r) r.checked = true;
            loadStaff();
        }
        renderCalendar();
        setStep(1);
    </script>
@endsection
