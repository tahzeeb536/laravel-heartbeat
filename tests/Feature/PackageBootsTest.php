<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Tahzeeb\Heartbeat\Facades\Heartbeat as HeartbeatFacade;
use Tahzeeb\Heartbeat\Heartbeat;

it('merges the package config', function (): void {
    expect(config('heartbeat.url'))->toBe('https://api.laravelheartbeat.com/v1')
        ->and(config('heartbeat.timeout'))->toBe(2)
        ->and(config('heartbeat.grace_period'))->toBe(60);
});

it('registers the Heartbeat singleton', function (): void {
    expect(app(Heartbeat::class))->toBeInstanceOf(Heartbeat::class);
});

it('resolves the Heartbeat facade to the same singleton', function (): void {
    expect(HeartbeatFacade::getFacadeRoot())->toBe(app(Heartbeat::class));
});

it('registers the heartbeat:test artisan command', function (): void {
    Http::fake();

    $this->artisan('heartbeat:test')->assertSuccessful();
});
