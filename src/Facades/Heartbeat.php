<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Facades;

use Illuminate\Support\Facades\Facade;
use Tahzeeb\Heartbeat\Heartbeat as HeartbeatClient;

final class Heartbeat extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return HeartbeatClient::class;
    }
}
