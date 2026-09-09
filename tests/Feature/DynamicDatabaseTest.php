<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynamicDatabaseTest extends TestCase
{
    public function test_production_request_returns_production_environment_header(): void
    {
        $response = $this->getJson('/up');

        $response->assertHeader('X-Environment', 'production');
    }

    public function test_demo_header_switches_environment_and_sets_response_header(): void
    {
        $response = $this->withHeaders([
            'X-Environment' => 'demo',
        ])->getJson('/up');

        $response->assertHeader('X-Environment', 'demo');
        $this->assertEquals('mysql_demo', DB::getDefaultConnection());
    }

    public function test_demo_origin_subdomain_switches_environment(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://demo-sso.polinus.cloud',
        ])->getJson('/up');

        $response->assertHeader('X-Environment', 'demo');
        $this->assertEquals('mysql_demo', DB::getDefaultConnection());
    }
}
