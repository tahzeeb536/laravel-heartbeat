<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Http;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HeartbeatClient
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $apiKey,
        private readonly int $timeout,
        private readonly bool $enabled,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $path, array $payload): void
    {
        $this->request($path, $payload, 'Heartbeat ping failed.');
    }

    /**
     * Like send(), but reports the outcome instead of swallowing it silently.
     * Used by commands that need to tell a human whether it worked.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ping(string $path, array $payload): HeartbeatResult
    {
        return $this->request($path, $payload, 'Heartbeat ping failed.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $monitors
     */
    public function sync(array $monitors): HeartbeatResult
    {
        return $this->request('monitors/sync', ['monitors' => $monitors], 'Heartbeat monitor sync failed.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function request(string $path, array $payload, string $logMessage): HeartbeatResult
    {
        if (! $this->enabled || blank($this->apiKey)) {
            return new HeartbeatResult(success: false, status: null, error: 'Heartbeat is disabled or no API key is set.');
        }

        try {
            $response = Http::withHeaders(['X-Heartbeat-Key' => $this->apiKey])
                ->connectTimeout($this->timeout)
                ->timeout($this->timeout)
                ->post($this->endpoint($path), $payload);

            return new HeartbeatResult(
                success: $response->successful(),
                status: $response->status(),
                error: $response->successful() ? null : "Received HTTP {$response->status()}.",
            );
        } catch (Throwable $e) {
            Log::warning($logMessage, [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return new HeartbeatResult(success: false, status: null, error: $e->getMessage());
        }
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->url, '/').'/'.ltrim($path, '/');
    }
}
