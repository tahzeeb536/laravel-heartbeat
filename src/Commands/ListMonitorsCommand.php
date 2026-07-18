<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Commands;

use Illuminate\Console\Command;
use Tahzeeb\Heartbeat\Data\Monitor;
use Tahzeeb\Heartbeat\Scheduling\ScheduleInspector;

final class ListMonitorsCommand extends Command
{
    protected $signature = 'heartbeat:list';

    protected $description = 'List monitors discovered from the application schedule';

    public function handle(ScheduleInspector $inspector): int
    {
        $monitors = $inspector->monitors();

        if ($monitors === []) {
            $this->components->warn('No scheduled tasks discovered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Slug', 'Name', 'Cron', 'Type'],
            array_map(fn (Monitor $monitor): array => [
                $monitor->slug,
                $monitor->name,
                $monitor->cronExpression,
                $monitor->type,
            ], $monitors),
        );

        return self::SUCCESS;
    }
}
