<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$logger = new Lab\ActivityLogger();
$logger->record('monitoring_ready', [
    'phase' => 'baseline',
]);

echo "Baseline log initialized in logs/events.jsonl" . PHP_EOL;