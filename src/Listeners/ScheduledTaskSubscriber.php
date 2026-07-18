<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Events\Dispatcher;
use Tahzeeb\Heartbeat\Heartbeat;
use Tahzeeb\Heartbeat\Support\MonitorName;

final class ScheduledTaskSubscriber
{
    public function __construct(private readonly Heartbeat $heartbeat) {}

    /**
     * Map of scheduler event => handler method.
     *
     * Registered as string class listeners (not [$this, ...]) so the container
     * resolves this subscriber - and the Heartbeat singleton it depends on -
     * lazily at dispatch time, rather than eagerly when the listener is
     * registered during boot.
     *
     * @return array<class-string, string>
     */
    public static function listeners(): array
    {
        return [
            ScheduledTaskStarting::class => 'handleStarting',
            ScheduledTaskFinished::class => 'handleFinished',
            ScheduledTaskFailed::class => 'handleFailed',
        ];
    }

    public function subscribe(Dispatcher $events): void
    {
        foreach (self::listeners() as $event => $method) {
            $events->listen($event, [self::class, $method]);
        }
    }

    public function handleStarting(ScheduledTaskStarting $event): void
    {
        $task = $event->task;

        if ($this->isIgnored($task)) {
            return;
        }

        $this->heartbeat->start(MonitorName::forTask($task));
    }

    public function handleFinished(ScheduledTaskFinished $event): void
    {
        $task = $event->task;

        if ($this->isIgnored($task)) {
            return;
        }

        $slug = MonitorName::forTask($task);

        if ($task->exitCode !== null && $task->exitCode !== 0) {
            $this->heartbeat->fail($slug, $task->exitCode);

            return;
        }

        $this->heartbeat->success($slug, (int) round($event->runtime * 1000));
    }

    public function handleFailed(ScheduledTaskFailed $event): void
    {
        $task = $event->task;

        if ($this->isIgnored($task)) {
            return;
        }

        $this->heartbeat->fail(MonitorName::forTask($task), $task->exitCode, $event->exception->getMessage());
    }

    private function isIgnored(Event $task): bool
    {
        return MonitorName::isIgnored($task, (array) config('heartbeat.ignore', []));
    }
}
