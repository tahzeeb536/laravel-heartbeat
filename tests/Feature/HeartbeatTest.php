<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tahzeeb\Heartbeat\Facades\Heartbeat;

it('sends a success ping with duration, host, and at', function (): void {
    Http::fake();

    Heartbeat::success('my-job', 1234);

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.laravelheartbeat.com/v1/ping/my-job'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Heartbeat-Key', 'test-api-key')
            && $request['duration_ms'] === 1234
            && $request['host'] !== null
            && $request['at'] !== null;
    });
});

it('sends a start ping to the start endpoint', function (): void {
    Http::fake();

    Heartbeat::start('my-job');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.laravelheartbeat.com/v1/ping/my-job/start'
        && $request->method() === 'POST'
    );
});

it('sends a fail ping with exit_code and output, truncated to 10KB', function (): void {
    Http::fake();

    $longOutput = str_repeat('x', 20000);

    Heartbeat::fail('my-job', 1, $longOutput);

    Http::assertSent(function (Request $request) use ($longOutput): bool {
        return $request->url() === 'https://api.laravelheartbeat.com/v1/ping/my-job/fail'
            && $request['exit_code'] === 1
            && strlen((string) $request['output']) <= 10240
            && $request['output'] !== $longOutput;
    });
});

it('sends the configured api key in the X-Heartbeat-Key header', function (): void {
    Http::fake();

    Heartbeat::success('my-job');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Heartbeat-Key', 'test-api-key'));
});

it('swallows connection failures and logs a warning instead of throwing', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));
    Log::spy();

    expect(fn () => Heartbeat::success('my-job'))->not->toThrow(Throwable::class);

    Log::shouldHaveReceived('warning')->once();
});

it('is a no-op when the api key is blank', function (): void {
    config(['heartbeat.api_key' => null]);

    Http::fake();

    Heartbeat::success('my-job');

    Http::assertNothingSent();
});

it('is a no-op when heartbeat is disabled', function (): void {
    config(['heartbeat.enabled' => false]);

    Http::fake();

    Heartbeat::success('my-job');

    Http::assertNothingSent();
});
