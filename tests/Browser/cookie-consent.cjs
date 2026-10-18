// Run with Playwright on NODE_PATH and an isolated local Laravel server.
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const base = process.env.CONSENT_TEST_URL || 'http://127.0.0.1:8876';
const key = 'esticly_cookie_consent';
const visitorKey = 'esticly_marketing_visitor_id';

(async () => {
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    let checks = 0;
    async function fixture(options = {}) {
        const context = await browser.newContext({viewport: {width: 1440, height: 900}, ...options});
        const requests = {google: 0, events: []};
        await context.route('**/gtag/js?*', async route => {
            requests.google++;
            await route.fulfill({contentType: 'application/javascript', body: 'document.cookie="_ga=test; Path=/"; document.cookie="_ga_N67PPKX0S8=test; Path=/";'});
        });
        await context.route('**/marketing-track', async route => {
            requests.events.push(route.request().postData());
            await route.fulfill({status: 201, contentType: 'application/json', body: '{"ok":true}'});
        });
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        return {context, page, requests, errors};
    }
    async function ready(page, path = '/en') {
        await page.goto(base + path, {waitUntil: 'networkidle'});
        await page.waitForFunction(() => !!window.EsticlyConsent);
    }
    async function settings(page) {
        await page.locator('footer [data-cookie-settings]').click();
        await page.locator('#cookieDialog').waitFor({state: 'visible'});
    }
    async function clickStore(page) {
        await page.locator('[data-marketing-store-click]').first().evaluate(el => {
            el.addEventListener('click', e => e.preventDefault(), {once: true});
            el.click();
        });
        await page.waitForLoadState('networkidle');
    }
    try {
        const {context, page, requests, errors} = await fixture();
        await ready(page);
        assert.equal(await page.locator('#cookieBanner').isVisible(), true);
        assert.equal(requests.google, 0);
        assert.equal(requests.events.length, 0);
        assert.equal(await page.evaluate(k => localStorage.getItem(k), visitorKey), null);
        await clickStore(page);
        assert.equal(requests.events.length, 0);
        await page.locator('#cookieBanner [data-cookie-settings]').click();
        assert.equal(await page.locator('#cookieAnalytics').isChecked(), false);
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#cookieDialog').isVisible(), false);
        assert.equal(await page.locator('#cookieBanner').isVisible(), true);
        await page.locator('#cookieBanner [data-cookie-choice="reject"]').click();
        await ready(page);
        assert.equal(await page.locator('#cookieBanner').isVisible(), false);
        assert.equal(requests.google, 0);
        assert.equal(requests.events.length, 0);
        checks++;

        await settings(page);
        await page.locator('#cookieAnalytics').check();
        await page.locator('#cookieSave').click();
        await page.waitForFunction(() => document.cookie.includes('_ga='));
        await page.waitForLoadState('networkidle');
        assert.equal(requests.google, 1);
        assert.equal(requests.events.length, 1);
        assert.match(requests.events[0], /v1/);
        assert.ok(await page.evaluate(k => localStorage.getItem(k), visitorKey));
        const config = await page.evaluate(() => Array.from(window.dataLayer.find(e => e[0] === 'config'))[2]);
        assert.equal(config.allow_google_signals, false);
        assert.ok(config.cookie_expires <= 180 * 86400);
        const storeResponse = page.waitForResponse(response => response.url().endsWith('/marketing-track'));
        await clickStore(page);
        await storeResponse;
        assert.equal(requests.events.length, 2);
        checks++;

        const second = await context.newPage();
        await ready(second);
        assert.equal(await second.locator('#cookieBanner').isVisible(), false);
        const beforeWithdraw = {...requests, events: requests.events.length};
        await settings(page);
        await page.locator('#cookieDialog [data-cookie-choice="reject"]').click();
        await page.waitForFunction(() => window.EsticlyConsent && !window.EsticlyConsent.allowsAnalytics());
        await second.waitForFunction(() => window.EsticlyConsent && !window.EsticlyConsent.allowsAnalytics());
        await page.waitForLoadState('networkidle');
        await second.waitForLoadState('networkidle');
        assert.equal(requests.google, beforeWithdraw.google);
        assert.equal(requests.events.length, beforeWithdraw.events);
        assert.equal(await page.evaluate(k => localStorage.getItem(k), visitorKey), null);
        assert.equal((await context.cookies()).filter(c => c.name.startsWith('_ga')).length, 0);
        assert.deepEqual(errors, []);
        checks++;
        await context.close();

        for (const stored of ['broken', JSON.stringify({version: 0, analytics: true, savedAt: Date.now()}), JSON.stringify({version: 1, analytics: true, savedAt: Date.now() - 181 * 86400000}), JSON.stringify({version: 1, analytics: true, savedAt: Date.now() + 86400000})]) {
            const f = await fixture();
            await f.context.addInitScript(({key, stored, visitorKey}) => {
                localStorage.setItem(key, stored);
                localStorage.setItem(visitorKey, 'legacy-id');
                document.cookie = '_ga=legacy; Path=/';
            }, {key, stored, visitorKey});
            await ready(f.page);
            assert.equal(f.requests.google, 0);
            assert.equal(f.requests.events.length, 0);
            assert.equal(await f.page.locator('#cookieBanner').isVisible(), true);
            assert.equal(await f.page.evaluate(k => localStorage.getItem(k), visitorKey), null);
            assert.equal((await f.context.cookies()).some(c => c.name === '_ga'), false);
            await f.context.close();
            checks++;
        }

        const blocked = await fixture();
        await blocked.context.addInitScript(() => {
            Storage.prototype.setItem = Storage.prototype.getItem = Storage.prototype.removeItem = () => { throw new Error('Storage blocked'); };
        });
        await ready(blocked.page);
        await blocked.page.locator('#cookieBanner [data-cookie-choice="reject"]').click();
        assert.equal(blocked.requests.google, 0);
        assert.deepEqual(blocked.errors, []);
        await blocked.context.close();
        checks++;

        for (const locale of ['en', 'uk', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt']) {
            for (const [width, height] of [[1440, 900], [390, 844], [320, 568]]) {
                const f = await fixture({viewport: {width, height}});
                await ready(f.page, '/' + locale);
                const banner = f.page.locator('#cookieBanner');
                const bounds = await banner.boundingBox();
                assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
                assert.equal(await banner.evaluate(el => el.scrollWidth <= el.clientWidth), true);
                for (const button of await banner.locator('button').all()) {
                    assert.equal(await button.evaluate(el => el.scrollWidth <= el.clientWidth), true);
                }
                if (locale === 'pl' && width !== 320) await f.page.screenshot({path: `/private/tmp/esticly-consent-${width}.png`});
                await banner.locator('[data-cookie-settings]').click();
                const dialog = f.page.locator('#cookieDialog');
                assert.equal(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth), true);
                if (locale === 'pl' && width === 390) await f.page.screenshot({path: '/private/tmp/esticly-consent-settings.png'});
                await f.page.locator('#cookieSave').click();
                assert.equal(f.requests.google, 0);
                assert.equal(f.requests.events.length, 0);
                assert.deepEqual(f.errors, []);
                await f.context.close();
                checks++;
            }
        }
        console.log(`PASS: ${checks} consent scenarios, 9 locales, desktop/mobile, no analytics before consent, rejection, withdrawal across tabs, legacy storage cleanup.`);
    } finally {
        await browser.close();
    }
})().catch(error => {console.error(error); process.exit(1);});
