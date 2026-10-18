<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_redirects_to_the_localized_landing_page(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')->get('/');

        $response->assertStatus(301)
            ->assertRedirect(route('marketing.localized', ['locale' => 'en']));
    }
}
