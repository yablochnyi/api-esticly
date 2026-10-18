(() => {
    'use strict';
    const key = 'esticly_cookie_consent';
    const version = 1;
    const lifetime = 180 * 24 * 60 * 60 * 1000;
    const measurementId = 'G-N67PPKX0S8';
    const disableKey = `ga-disable-${measurementId}`;
    const banner = document.getElementById('cookieBanner');
    const dialog = document.getElementById('cookieDialog');
    const analytics = document.getElementById('cookieAnalytics');
    if (!banner || !dialog || !analytics) return;
    let choice = null;
    let googleStarted = false;
    let timer;

    function valid(value) {
        return value?.version === version && typeof value.analytics === 'boolean'
            && Number.isFinite(value.savedAt) && value.savedAt <= Date.now()
            && value.savedAt > Date.now() - lifetime;
    }
    function read() {
        try {
            const value = JSON.parse(localStorage.getItem(key));
            return valid(value) ? value : null;
        } catch (_) { return null; }
    }
    function clearAnalytics() {
        try { localStorage.removeItem('esticly_marketing_visitor_id'); } catch (_) {}
        const hostParts = location.hostname.split('.');
        const domains = ['', ...hostParts.map((_, i) => `; Domain=${hostParts.slice(i).join('.')}`)];
        const paths = ['/', ...location.pathname.split('/').filter(Boolean).map((_, i, parts) => `/${parts.slice(0, i + 1).join('/')}`)];
        document.cookie.split(';').forEach((cookie) => {
            const name = cookie.split('=')[0].trim();
            if (!/^(_ga(?:_|$)|_gid$|_gat(?:_|$))/.test(name)) return;
            domains.forEach((domain) => paths.forEach((path) => {
                document.cookie = `${name}=; Max-Age=0; Path=${path}${domain}; SameSite=Lax`;
            }));
        });
    }
    function allowsAnalytics() {
        return valid(choice) && choice.analytics;
    }
    function startGoogle() {
        if (googleStarted || !allowsAnalytics()) return;
        googleStarted = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = function () { if (allowsAnalytics()) window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', {
            analytics_storage: 'granted', ad_storage: 'denied',
            ad_user_data: 'denied', ad_personalization: 'denied',
        });
        window.gtag('js', new Date());
        window.gtag('config', measurementId, {
            cookie_expires: Math.max(1, Math.floor((choice.savedAt + lifetime - Date.now()) / 1000)),
            cookie_update: false,
            allow_google_signals: false,
            allow_ad_personalization_signals: false,
        });
        const script = document.createElement('script');
        script.async = true;
        script.src = `https://www.googletagmanager.com/gtag/js?id=${measurementId}`;
        document.head.appendChild(script);
    }
    function apply() {
        const allowed = allowsAnalytics();
        window[disableKey] = !allowed;
        banner.hidden = valid(choice);
        window.dispatchEvent(new CustomEvent('esticly:consent', {detail: {analytics: allowed}}));
        clearTimeout(timer);
        if (valid(choice)) timer = setTimeout(refresh, Math.min(choice.savedAt + lifetime - Date.now() + 1, 86400000));
        if (allowed) startGoogle();
        else {
            clearAnalytics();
            // Reload removes an already running third-party library after withdrawal.
            if (googleStarted) location.reload();
        }
    }
    function refresh() {
        choice = read();
        apply();
    }
    function save(accepted) {
        choice = {version, analytics: accepted, savedAt: Date.now()};
        try { localStorage.setItem(key, JSON.stringify(choice)); } catch (_) {}
        dialog.close();
        apply();
    }
    window.EsticlyConsent = Object.freeze({allowsAnalytics});
    document.querySelectorAll('[data-cookie-settings]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => {
            analytics.checked = allowsAnalytics();
            dialog.showModal();
        });
    });
    document.querySelectorAll('[data-cookie-choice]').forEach((button) => {
        button.addEventListener('click', () => save(button.dataset.cookieChoice === 'accept'));
    });
    document.getElementById('cookieSave').addEventListener('click', () => save(analytics.checked));
    document.getElementById('cookieClose').addEventListener('click', () => dialog.close());
    window.addEventListener('storage', (event) => { if (event.key === key || event.key === null) refresh(); });
    window.addEventListener('pageshow', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    refresh();
})();
