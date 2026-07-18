<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Commands;

use Illuminate\Console\Command;
use Tahzeeb\Heartbeat\Http\HeartbeatClient;

final class TestPingCommand extends Command
{
    protected $signature = 'heartbeat:test {--slug=heartbeat-test : The monitor slug to ping}';

    protected $description = 'Send a test ping to verify your Heartbeat API key and connectivity';

    public function handle(HeartbeatClient $client): int
    {
        if (! (bool) config('heartbeat.enabled')) {
            $this->components->warn('Heartbeat is disabled (HEARTBEAT_ENABLED=false). No ping was sent.');

            return self::FAILURE;
        }

        if (blank(config('heartbeat.api_key'))) {
            $this->components->warn('No Heartbeat API key is set (HEARTBEAT_API_KEY). No ping was sent.');

            return self::FAILURE;
        }

        $slug = (string) $this->option('slug');
        $path = 'ping/'.rawurlencode($slug);
        $endpoint = rtrim((string) config('heartbeat.url'), '/').'/'.$path;

        $this->components->info("Sending test ping to {$endpoint}...");

        $result = $client->ping($path, [
            'host' => gethostname() ?: 'unknown',
            'at' => now()->toIso8601String(),
            'duration_ms' => 0,
        ]);

        if ($result->success) {
            $this->components->info("Success (HTTP {$result->status}). Your Heartbeat API key and connectivity are working.");

            return self::SUCCESS;
        }

        $status = $result->status !== null ? "HTTP {$result->status}" : 'no response';
        $this->components->error("Failed to reach Heartbeat ({$status}): {$result->error}");

        return self::FAILURE;
    }
}
