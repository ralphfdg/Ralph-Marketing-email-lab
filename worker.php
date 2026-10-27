<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$db        = (new Lab\Database())->connect();
$sqs       = Lab\Settings::queue();
$queueUrl  = Lab\Settings::required('SQS_QUEUE_URL');
$logger    = new Lab\ActivityLogger();
$history   = new Lab\EmailHistory($db);
$sender    = new Lab\EmailSender(Lab\Settings::mailtrap());
$delay     = Lab\Settings::sendDelaySeconds();

$processor = new Lab\MarketingProcessor($db, $history, $sender, $logger, $delay);
$worker    = new Lab\QueueWorker($sqs, $queueUrl, $processor, $logger);

$worker->run();