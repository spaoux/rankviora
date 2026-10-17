<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        // Container environment must never redirect RefreshDatabase to the local app database.
        $settings = ['DATAFORSEO_MODE' => 'mock', 'DATAFORSEO_LOGIN' => '', 'DATAFORSEO_PASSWORD' => '', 'DATAFORSEO_SANDBOX_ENABLED' => 'false', 'DATAFORSEO_LIVE_ENABLED' => 'false', 'DATAFORSEO_LIVE_APPROVAL' => '', 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BCRYPT_ROUNDS' => '4'];
        foreach ($settings as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        if ($app->environment() !== 'testing' || $app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Tests require the isolated SQLite memory database. Clear cached configuration.');
        }

        return $app;
    }
}
