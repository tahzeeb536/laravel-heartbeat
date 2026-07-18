<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Http;

final class HeartbeatResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?int $status,
        public readonly ?string $error,
    ) {}
}
