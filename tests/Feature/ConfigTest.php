<?php

namespace Tests\Feature;

use Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_app_timezone_is_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
    }

    public function test_carbon_now_uses_configured_timezone(): void
    {
        $this->assertSame('Asia/Jakarta', now()->timezoneName);
    }
}
