<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$db = (new Lab\Database())->connect();
$sqs = Lab\Settings::queue();
$queueUrl = Lab\Settings::required('SQS_QUEUE_URL');
$logger = new Lab\ActivityLogger();

$publisher = new Lab\QueuePublisher($db, $sqs, $queueUrl, $logger);
$published = $publisher->publishPending();

echo "Successfully published {$published} PENDING requests to Amazon SQS." . PHP_EOL;