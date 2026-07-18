<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Scheduling;

use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Tahzeeb\Heartbeat\Data\Monitor;
use Tahzeeb\Heartbeat\Support\MonitorName;

final class ScheduleInspector
{
    public function __construct(private readonly Schedule $schedule) {}

    /**
     * @return array<int, Monitor>
     */
    public function monitors(): array
    {
        $ignore = (array) config('heartbeat.ignore', []);
        $gracePeriod = (int) config('heartbeat.grace_period', 60);

        $monitors = [];

        foreach ($this->schedule->events() as $event) {
            if (MonitorName::isIgnored($event, $ignore)) {
                continue;
            }

            $monitors[] = new Monitor(
                slug: MonitorName::forTask($event),
                name: MonitorName::nameForTask($event),
                type: 'scheduled',
                cronExpression: $event->expression,
                timezone: self::timezoneName($event->timezone),
                gracePeriod: $gracePeriod,
            );
        }

        return $monitors;
    }

    /**
     * Laravel's own docblock for Event::$timezone omits `null`, even though the
     * constructor default is null and most scheduled tasks never call ->timezone().
     * Typing this boundary explicitly (rather than trusting the vendor docblock)
     * keeps the null case real for PHPStan instead of "unreachable".
     *
     * @param  DateTimeZone|string|null  $timezone
     */
    private static function timezoneName(mixed $timezone): ?string
    {
        return match (true) {
            $timezone instanceof DateTimeZone => $timezone->getName(),
            is_string($timezone) => $timezone,
            default => null,
        };
    }
}
