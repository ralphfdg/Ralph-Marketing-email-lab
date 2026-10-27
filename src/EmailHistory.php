<?php

declare(strict_types=1);

namespace Lab;

use PDO;

final class EmailHistory
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function isComplete(string $campaignId, string $customerId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT status FROM sent_marketing_emails WHERE campaign_id = ? AND customer_id = ? LIMIT 1"
        );
        $stmt->execute([$campaignId, $customerId]);
        $status = $stmt->fetchColumn();

        return in_array($status, ['SENT', 'SKIPPED'], true);
    }

    public function saveSent(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        string $recipientEmail,
        string $subject,
        string $provider,
        string $messageId,
        string $sentAt
    ): void {
        $this->upsert(
            $campaignId,
            $customerId,
            $requestId,
            $promoCode,
            $recipientEmail,
            $subject,
            'SENT',
            $provider,
            $messageId,
            null,
            $sentAt,
            $sentAt
        );
    }

    public function saveSkipped(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        ?string $recipientEmail,
        string $subject,
        string $reason
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $this->upsert(
            $campaignId,
            $customerId,
            $requestId,
            $promoCode,
            $recipientEmail,
            $subject,
            'SKIPPED',
            null,
            null,
            $reason,
            $now,
            null
        );
    }

    public function saveFailed(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        ?string $recipientEmail,
        string $subject,
        string $reason
    ): void {
        $now = gmdate('Y-m-d H:i:s');
        $this->upsert(
            $campaignId,
            $customerId,
            $requestId,
            $promoCode,
            $recipientEmail,
            $subject,
            'FAILED',
            'Mailtrap',
            null,
            $reason,
            $now,
            null
        );
    }

    private function upsert(
        string $campaignId,
        string $customerId,
        string $requestId,
        string $promoCode,
        ?string $recipientEmail,
        string $subject,
        string $status,
        ?string $provider,
        ?string $messageId,
        ?string $reason,
        string $processedAt,
        ?string $sentAt
    ): void {
        $sql = <<<SQL
            INSERT INTO sent_marketing_emails (
                campaign_id, customer_id, request_id, promo_code,
                recipient_email, subject, status, provider,
                message_id, reason, processed_at, sent_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                request_id = VALUES(request_id),
                promo_code = VALUES(promo_code),
                recipient_email = VALUES(recipient_email),
                subject = VALUES(subject),
                status = VALUES(status),
                provider = VALUES(provider),
                message_id = VALUES(message_id),
                reason = VALUES(reason),
                processed_at = VALUES(processed_at),
                sent_at = VALUES(sent_at)
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $campaignId,
            $customerId,
            $requestId,
            $promoCode,
            $recipientEmail,
            $subject,
            $status,
            $provider,
            $messageId,
            $reason,
            $processedAt,
            $sentAt,
        ]);
    }
}