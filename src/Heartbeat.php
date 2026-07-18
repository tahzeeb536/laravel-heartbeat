<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat;

use Tahzeeb\Heartbeat\Http\HeartbeatClient;

final class Heartbeat
{
    public function __construct(private readonly HeartbeatClient $client) {}

    public function start(string $slug): void
    {
        $this->client->send($this->path($slug, 'start'), $this->basePayload());
    }

    public function success(string $slug, ?int $durationMs = null): void
    {
        $payload = $this->basePayload();
        $payload['duration_ms'] = $durationMs;

        $this->client->send($this->path($slug), $payload);
    }

    public function fail(string $slug, ?int $exitCode = null, ?string $output = null): void
    {
        $payload = $this->basePayload();
        $payload['exit_code'] = $exitCode;
        $payload['output'] = $output === null ? null : substr($output, 0, 10240);

        $this->client->send($this->path($slug, 'fail'), $payload);
    }

    private function path(string $slug, ?string $suffix = null): string
    {
        $path = 'ping/'.rawurlencode($slug);

        return $suffix === null ? $path : $path.'/'.$suffix;
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(): array
    {
        return [
            'host' => gethostname() ?: 'unknown',
            'at' => now()->toIso8601String(),
        ];
    }
}
