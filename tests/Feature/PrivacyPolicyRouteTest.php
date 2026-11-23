<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyRouteTest extends TestCase
{
    public function test_privacy_policy_page_is_accessible(): void
    {
        $response = $this->get('/privacy-policy');

        $response->assertStatus(200);
        $response->assertSee('Kebijakan Privasi');
        $response->assertSee('myIn-U Staff');
        $response->assertSee('Politeknik Indonusa Surakarta');
    }

    public function test_kebijakan_privasi_alias_page_is_accessible(): void
    {
        $response = $this->get('/kebijakan-privasi');

        $response->assertStatus(200);
        $response->assertSee('Kebijakan Privasi');
        $response->assertSee('myIn-U Staff');
        $response->assertSee('Politeknik Indonusa Surakarta');
    }
}
