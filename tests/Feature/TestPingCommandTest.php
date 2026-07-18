<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('sends a success test ping to the default slug endpoint with the api key header', function (): void {
    Http::fake();

    $this->artisan('heartbeat:test')
        ->assertSuccessful()
        ->expectsOutputToContain('https://api.laravelheartbeat.com/v1/ping/heartbeat-test')
        ->expectsOutputToContain('Success');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://api.laravelheartbeat.com/v1/ping/heartbeat-test'
            && $request->method() === 'POST'
            && $request->hasHeader('X-Heartbeat-Key', 'test-api-key');
    });
});

it('sends the test ping to a custom slug when --slug is given', function (): void {
    Http::fake();

    $this->artisan('heartbeat:test', ['--slug' => 'custom-check'])->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.laravelheartbeat.com/v1/ping/custom-check');
});

it('reports a connection failure without throwing', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $this->artisan('heartbeat:test')
        ->assertFailed()
        ->expectsOutputToContain('Failed to reach Heartbeat');
});

it('reports a non-2xx response as a failure', function (): void {
    Http::fake(fn () => Http::response('', 500));

    $this->artisan('heartbeat:test')
        ->assertFailed()
        ->expectsOutputToContain('500');
});

it('does not send a request and reports it is disabled when heartbeat is disabled', function (): void {
    config(['heartbeat.enabled' => false]);

    Http::fake();

    $this->artisan('heartbeat:test')
        ->assertFailed()
        ->expectsOutputToContain('disabled');

    Http::assertNothingSent();
});

it('does not send a request and reports no api key when the api key is blank', function (): void {
    config(['heartbeat.api_key' => null]);

    Http::fake();

    $this->artisan('heartbeat:test')
        ->assertFailed()
        ->expectsOutputToContain('No Heartbeat API key');

    Http::assertNothingSent();
});
