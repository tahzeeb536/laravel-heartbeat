<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Data;

final class Monitor
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $cronExpression,
        public readonly ?string $timezone,
        public readonly int $gracePeriod,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'type' => $this->type,
            'cron_expression' => $this->cronExpression,
            'timezone' => $this->timezone,
            'grace_period' => $this->gracePeriod,
        ];
    }
}
