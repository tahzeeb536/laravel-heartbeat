<?php

declare(strict_types=1);

namespace Tahzeeb\Heartbeat\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Str;

final class MonitorName
{
    public static function forTask(Event $task): string
    {
        $name = self::nameForTask($task);

        $identity = $task->command !== null
            ? substr(sha1(self::normalizeCommandSignature($task->command).'|'.$task->expression), 0, 8)
            : substr(sha1(($task->command ?? '').'|'.$task->expression.'|'.$task->mutexName()), 0, 8);

        $slug = Str::slug($name);

        $base = $slug !== '' ? $slug : 'task';

        return $base.'-'.$identity;
    }

    /**
     * Derive the human-readable display name for a scheduled task: the
     * user-provided description when set, otherwise a normalized, platform-
     * independent signature for command tasks, or the summary display for
     * closure/callback tasks (which have no command to normalize).
     */
    public static function nameForTask(Event $task): string
    {
        if (is_string($task->description) && trim($task->description) !== '') {
            return $task->description;
        }

        return $task->command !== null
            ? self::normalizeCommandSignature($task->command)
            : $task->getSummaryForDisplay();
    }

    /**
     * Reduce a scheduled command string to a platform-independent signature by
     * stripping the OS-specific output redirection and the php-binary/artisan
     * prefix, so the same task yields the same slug regardless of the OS or
     * machine it was defined on.
     */
    private static function normalizeCommandSignature(string $command): string
    {
        $command = preg_replace('/\s*>>?\s*(?:"[^"]*"|\'[^\']*\')\s*2>&1\s*$/', '', $command) ?? $command;

        $command = Event::normalizeCommand($command);

        $command = preg_replace('/^php\s+artisan\s+/', '', $command) ?? $command;

        return trim($command, " \t\n\r\0\x0B\"'");
    }

    /**
     * @param  array<int, string>  $ignore
     */
    public static function isIgnored(Event $task, array $ignore): bool
    {
        if ($ignore === []) {
            return false;
        }

        $slug = self::forTask($task);

        foreach ($ignore as $pattern) {
            $pattern = (string) $pattern;

            if ($pattern === $slug || str_starts_with($slug, $pattern.'-')) {
                return true;
            }

            if ($task->command !== null && (
                str_contains($task->command, $pattern)
                || str_contains(self::normalizeCommandSignature($task->command), $pattern)
            )) {
                return true;
            }
        }

        return false;
    }

    public static function forQueueJob(string $jobClass): string
    {
        $slug = Str::slug($jobClass);

        $identity = substr(sha1($jobClass), 0, 8);

        $base = $slug !== '' ? $slug : 'job';

        return $base.'-'.$identity;
    }

    /**
     * @param  array<int, string>  $ignore
     */
    public static function isQueueJobIgnored(string $jobClass, array $ignore): bool
    {
        if ($ignore === []) {
            return false;
        }

        $slug = self::forQueueJob($jobClass);

        foreach ($ignore as $pattern) {
            $pattern = (string) $pattern;

            if ($pattern === $slug || str_starts_with($slug, $pattern.'-')) {
                return true;
            }

            if (str_contains($jobClass, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
