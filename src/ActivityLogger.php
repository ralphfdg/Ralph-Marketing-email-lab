<?php

declare(strict_types=1);

namespace Lab;

final class ActivityLogger
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? dirname(__DIR__) . '/logs/events.jsonl';
    }

    public function record(string $event, array $context = []): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $entry = array_merge(
            [
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'event'     => $event,
            ],
            $context
        );

        file_put_contents(
            $this->logFile,
            json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}