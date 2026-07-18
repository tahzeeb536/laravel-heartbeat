<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Tahzeeb\Heartbeat\Commands\ListMonitorsCommand;
use Tahzeeb\Heartbeat\Commands\SyncMonitorsCommand;
use Tahzeeb\Heartbeat\Commands\TestPingCommand;
use Tahzeeb\Heartbeat\Http\HeartbeatClient;
use Tahzeeb\Heartbeat\Listeners\PingOnJobFailed;
use Tahzeeb\Heartbeat\Listeners\ScheduledTaskSubscriber;
use Tahzeeb\Heartbeat\Scheduling\ScheduleInspector;

final class HeartbeatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/heartbeat.php', 'heartbeat');

        $this->app->singleton(HeartbeatClient::class, fn (): HeartbeatClient => new HeartbeatClient(
            url: (string) config('heartbeat.url'),
            apiKey: config('heartbeat.api_key'),
            timeout: (int) config('heartbeat.timeout'),
            enabled: (bool) config('heartbeat.enabled'),
        ));

        $this->app->singleton(Heartbeat::class);

        $this->app->singleton(ScheduleInspector::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/heartbeat.php' => $this->app->configPath('heartbeat.php'),
            ], 'heartbeat-config');

            $this->commands([
                TestPingCommand::class,
                ListMonitorsCommand::class,
                SyncMonitorsCommand::class,
            ]);
        }

        $this->app->booted(function (): void {
            if (! $this->monitoringEnabled()) {
                return;
            }

            if ((bool) config('heartbeat.monitor_scheduled_tasks', true)) {
                foreach (ScheduledTaskSubscriber::listeners() as $event => $method) {
                    Event::listen($event, [ScheduledTaskSubscriber::class, $method]);
                }
            }

            if ((bool) config('heartbeat.monitor_queue_failures', true)) {
                Event::listen(JobFailed::class, [PingOnJobFailed::class, 'handle']);
            }
        });
    }

    /**
     * Whether pings/hooks should be registered at all, per the config
     * no-op rules: disabled, missing api key, or wrong environment.
     */
    private function monitoringEnabled(): bool
    {
        if (! config('heartbeat.enabled')) {
            return false;
        }

        if (blank(config('heartbeat.api_key'))) {
            return false;
        }

        return $this->app->environment((array) config('heartbeat.environments', []));
    }
}
