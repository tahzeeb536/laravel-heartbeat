<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Tahzeeb\Heartbeat\Facades\Heartbeat;
use Tahzeeb\Heartbeat\HeartbeatServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            HeartbeatServiceProvider::class,
        ];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Heartbeat' => Heartbeat::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('heartbeat.api_key', 'test-api-key');
        $app['config']->set('heartbeat.environments', ['testing']);
    }
}
