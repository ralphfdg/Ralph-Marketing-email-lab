<?php

declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use PDO;
use Throwable;

final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {
    }

    public function publishPending(int $limit = 50): int
    {
        $selectSql = <<<SQL
            SELECT request_id, campaign_id, customer_id, promo_code, subject, body
            FROM marketing_outbox
            WHERE status = 'PENDING'
            LIMIT ?
        SQL;

        $stmt = $this->db->prepare($selectSql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $updateSql = <<<SQL
            UPDATE marketing_outbox
            SET status = 'QUEUED',
                queued_at = :queued_at,
                sqs_message_id = :sqs_message_id
            WHERE request_id = :request_id
        SQL;
        $updateStmt = $this->db->prepare($updateSql);

        $publishedCount = 0;

        foreach ($rows as $row) {
            $payload = [
                'requestId'  => $row['request_id'],
                'campaignId' => $row['campaign_id'],
                'customerId' => $row['customer_id'],
                'promoCode'  => $row['promo_code'],
                'subject'    => $row['subject'],
                'body'       => $row['body'],
            ];

            try {
                // TODO Q1: Call the injected SQS client's sendMessage() method.
                $result = $this->sqs->sendMessage([
                    'QueueUrl'    => $this->queueUrl,
                    'MessageBody' => json_encode($payload, JSON_THROW_ON_ERROR),
                ]);

                $sqsMessageId = (string) $result->get('MessageId');
                $queuedAt = gmdate('Y-m-d H:i:s');

                // TODO Q2: Only after acknowledgment, execute the update statement.
                $updateStmt->execute([
                    ':queued_at'      => $queuedAt,
                    ':sqs_message_id' => $sqsMessageId,
                    ':request_id'     => $row['request_id'],
                ]);

                // TODO M1: Record a queued log event.
                $this->logger->record('queued', [
                    'requestId'    => $row['request_id'],
                    'campaignId'   => $row['campaign_id'],
                    'customerId'   => $row['customer_id'],
                    'sqsMessageId' => $sqsMessageId,
                ]);

                $publishedCount++;
            } catch (Throwable $e) {
                // Leave unsuccessful or unconfirmed sends PENDING[cite: 15]
                $this->logger->record('enqueue_failed', [
                    'requestId'  => $row['request_id'],
                    'campaignId' => $row['campaign_id'],
                    'error'      => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $publishedCount;
    }
}

