<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    public function test_public_pages_offer_translated_consent_without_loading_google(): void
    {
        $keys = array_keys(require lang_path('en/cookies.php'));
        foreach (['en', 'uk', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt'] as $locale) {
            $translations = require lang_path($locale.'/cookies.php');
            $this->assertSame($keys, array_keys($translations));
            foreach ($translations as $text) {
                $this->assertNotEmpty($text);
            }

            $this->get('/'.$locale)->assertOk()
                ->assertSee($translations['title'])
                ->assertSee($translations['reject'])
                ->assertSee('id="cookieAnalytics" type="checkbox"', false)
                ->assertSee('cookie-consent.js', false)
                ->assertDontSee('src="https://www.googletagmanager.com', false);

            $this->get('/'.$locale.'/privacy')->assertOk()
                ->assertSee('id="cookies"', false)
                ->assertSee($translations['retention']);
        }
    }

    public function test_marketing_events_require_explicit_analytics_consent(): void
    {
        foreach ([null, false, true, 'v0', 'denied'] as $consent) {
            $this->postJson('/marketing-track', [
                'event' => 'landing_view', 'analytics_consent' => $consent,
            ])->assertForbidden();
        }
    }

    public function test_consented_marketing_events_still_work(): void
    {
        $migration = require database_path('migrations/2026_06_15_000001_create_public_marketing_events_table.php');
        $migration->up();
        try {
            $this->postJson('/marketing-track', [
                'event' => 'landing_view', 'analytics_consent' => 'v1',
                'locale' => 'pl', 'visitor_id' => 'test-browser',
            ])->assertCreated()->assertJson(['ok' => true]);
            $this->assertDatabaseHas('public_marketing_events', [
                'event' => 'landing_view', 'locale' => 'pl',
                'visitor_id_hash' => hash_hmac('sha256', 'test-browser', config('app.key')),
            ]);
        } finally {
            Schema::dropIfExists('public_marketing_events');
        }
    }
}
