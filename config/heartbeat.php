<?php

declare(strict_types=1);

return [

    'enabled' => env('HEARTBEAT_ENABLED', true),

    'api_key' => env('HEARTBEAT_API_KEY'),

    'url' => env('HEARTBEAT_URL', 'https://api.laravelheartbeat.com/v1'),

    'timeout' => env('HEARTBEAT_TIMEOUT', 2),

    'monitor_scheduled_tasks' => true,

    'monitor_queue_failures' => true,

    'grace_period' => 60,

    'ignore' => [],

    'environments' => ['production'],

];
