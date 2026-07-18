<?php

declare(strict_types=1);

use Tahzeeb\Heartbeat\Tests\QueueMonitoringDisabledTestCase;
use Tahzeeb\Heartbeat\Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(QueueMonitoringDisabledTestCase::class)->in('QueueDisabled');
