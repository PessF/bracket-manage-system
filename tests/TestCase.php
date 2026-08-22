<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Docker exports MySQL variables to the app container. Override Laravel's
     * test configuration before RefreshDatabase runs so development data can
     * never be touched by the test suite.
     */
    public function createApplication(): Application
    {
        foreach ([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'SESSION_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
        ] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        /** @var Application $application */
        $application = parent::createApplication();

        return $application;
    }
}
