<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeveloperCreditTest extends TestCase
{
    public function test_landing_pages_show_the_translated_developer_credit(): void
    {
        foreach (['en', 'uk', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt'] as $locale) {
            $destination = $locale === 'cs' ? 'en' : $locale;
            $label = trans('landing.footer.developed_by', [], $locale);
            $this->assertNotSame('landing.footer.developed_by', $label);

            $this->get('/'.$locale)->assertOk()
                ->assertSee($label)
                ->assertSee('class="vendra-credit"', false)
                ->assertSee('https://getvendra.app/'.$destination.'?utm_source=esticly', false);
        }
    }

    public function test_booking_uses_the_same_credit_partial(): void
    {
        $this->assertStringContainsString(
            "@include('partials.vendra-credit')",
            file_get_contents(resource_path('views/booking/book.blade.php')),
        );

        $html = view('partials.vendra-credit', ['currentLocale' => 'pl'])->render();
        $this->assertStringContainsString('https://getvendra.app/pl?', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
    }
}
