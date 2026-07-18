<?php

declare(strict_types=1);

use Tahzeeb\Heartbeat\Support\MonitorName;

it('returns a slug matching the allowed slug shape', function (): void {
    $slug = MonitorName::forQueueJob('App\\Jobs\\SendWelcomeEmail');

    expect($slug)->toMatch('/^[a-z0-9-]+$/');
});

it('derives a stable slug for the same job class', function (): void {
    $first = MonitorName::forQueueJob('App\\Jobs\\SendWelcomeEmail');
    $second = MonitorName::forQueueJob('App\\Jobs\\SendWelcomeEmail');

    expect($first)->toBe($second);
});

it('derives different slugs for different job classes', function (): void {
    $first = MonitorName::forQueueJob('App\\Jobs\\SendWelcomeEmail');
    $second = MonitorName::forQueueJob('App\\Jobs\\ProcessPayment');

    expect($first)->not->toBe($second);
});
