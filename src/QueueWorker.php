<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use Throwable;

final class QueueWorker
{
    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger
    ) {
    }

    public function run(): void
    {
        echo "QueueWorker listening on {$this->queueUrl} (Press Ctrl+C to stop)..." . PHP_EOL;

        while (true) {
            $result = $this->sqs->receiveMessage([
                'QueueUrl'            => $this->queueUrl,
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds'     => 20,
                'AttributeNames'      => ['All'],
            ]);

            $messages = $result->get('Messages') ?? [];
            if (empty($messages)) {
                continue;
            }

            foreach ($messages as $message) {
                $this->handle($message);
            }
        }
    }

    public function handle(array $message): void
    {
        $sqsMessageId  = (string) ($message['MessageId'] ?? '');
        $receiptHandle = (string) ($message['ReceiptHandle'] ?? '');
        $rawBody       = (string) ($message['Body'] ?? '');
        $phase         = 'decode';
        $job           = [];

        $this->logger->record('received', [
            'sqsMessageId' => $sqsMessageId,
        ]);

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('Message body must decode to a JSON object.');
            }
            $job = $decoded;

            $phase = 'process';
            // TODO W1: Invoke the injected processor with this job.
            $this->processor->process($job);

            // TODO M3: Change the operation phase to 'delete' before acknowledgment.
            $phase = 'delete';

            // TODO W2: Delete the SQS message using the queue URL and ReceiptHandle.
            $this->sqs->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);

            // TODO M3: Log 'deleted' only after DeleteMessage succeeds.
            $this->logger->record('deleted', [
                'requestId'    => $job['requestId'] ?? null,
                'campaignId'   => $job['campaignId'] ?? null,
                'customerId'   => $job['customerId'] ?? null,
                'sqsMessageId' => $sqsMessageId,
            ]);

            echo sprintf(
                "[%s] Processed & acknowledged request %s (customer %s)%s",
                gmdate('H:i:s'),
                $job['requestId'] ?? 'unknown',
                $job['customerId'] ?? 'unknown',
                PHP_EOL
            );
        } catch (Throwable $e) {
            $eventName = ($phase === 'delete') ? 'ack_failed' : 'processing_failed';

            $context = [
                'sqsMessageId' => $sqsMessageId,
                'phase'        => $phase,
                'error'        => $e->getMessage(),
            ];
            if (!empty($job['requestId'])) {
                $context['requestId'] = $job['requestId'];
            }
            if (!empty($job['campaignId'])) {
                $context['campaignId'] = $job['campaignId'];
            }
            if (!empty($job['customerId'])) {
                $context['customerId'] = $job['customerId'];
            }

            $this->logger->record($eventName, $context);

            echo sprintf(
                "[%s] ERROR (%s) on SQS Message %s: %s%s",
                gmdate('H:i:s'),
                $phase,
                $sqsMessageId,
                $e->getMessage(),
                PHP_EOL
            );
        }
    }
}