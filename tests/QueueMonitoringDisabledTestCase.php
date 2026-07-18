<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Tests;

abstract class QueueMonitoringDisabledTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('heartbeat.monitor_queue_failures', false);
    }
}
