<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // The suite must always run in the 'testing' environment. phpunit.xml
        // declares APP_ENV=testing, but if the host shell exports APP_ENV (this
        // sandbox exports APP_ENV=local) that value lands in $_SERVER — and with
        // variables_order lacking 'E', Laravel's env repository reads $_SERVER
        // ahead of putenv/phpunit.xml, shadowing the intended environment. That
        // silently disabled test-only behaviour (e.g. Filament's form fill for
        // unit tests). Normalize all sources before the app boots.
        $_SERVER['APP_ENV'] = 'testing';
        $_ENV['APP_ENV'] = 'testing';
        putenv('APP_ENV=testing');

        parent::setUp();
    }
}
