<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Commands;

use Illuminate\Console\Command;
use Tahzeeb\Heartbeat\Data\Monitor;
use Tahzeeb\Heartbeat\Http\HeartbeatClient;
use Tahzeeb\Heartbeat\Scheduling\ScheduleInspector;

final class SyncMonitorsCommand extends Command
{
    protected $signature = 'heartbeat:sync';

    protected $description = 'Sync discovered monitors to your Heartbeat dashboard';

    public function handle(ScheduleInspector $inspector, HeartbeatClient $client): int
    {
        $monitors = $inspector->monitors();

        if ($monitors === []) {
            $this->components->warn('No scheduled tasks discovered. Nothing to sync.');

            return self::SUCCESS;
        }

        if (! (bool) config('heartbeat.enabled')) {
            $this->components->warn('Heartbeat is disabled (HEARTBEAT_ENABLED=false). No monitors were synced.');

            return self::FAILURE;
        }

        if (blank(config('heartbeat.api_key'))) {
            $this->components->warn('No Heartbeat API key is set (HEARTBEAT_API_KEY). No monitors were synced.');

            return self::FAILURE;
        }

        $result = $client->sync(array_map(fn (Monitor $monitor): array => $monitor->toArray(), $monitors));

        if ($result->success) {
            $this->components->info(sprintf('Synced %d monitor(s) to Heartbeat.', count($monitors)));

            return self::SUCCESS;
        }

        $status = $result->status !== null ? "HTTP {$result->status}" : 'no response';
        $this->components->error("Failed to sync monitors ({$status}): {$result->error}");

        return self::FAILURE;
    }
}
