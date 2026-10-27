<?php

declare(strict_types=1);

namespace Lab;

use InvalidArgumentException;
use PDO;
use Throwable;

final class MarketingProcessor
{
    public function __construct(
        private PDO $db,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $delaySeconds = 11
    ) {
    }

    public function process(array $job): void
    {
        $requestId  = trim((string) ($job['requestId'] ?? ''));
        $campaignId = trim((string) ($job['campaignId'] ?? ''));
        $customerId = trim((string) ($job['customerId'] ?? ''));
        $promoCode  = trim((string) ($job['promoCode'] ?? ''));
        $subject    = trim((string) ($job['subject'] ?? ''));
        $body       = trim((string) ($job['body'] ?? ''));

        if ($requestId === '' || $campaignId === '' || $customerId === '' || $promoCode === '' || $subject === '' || $body === '') {
            throw new InvalidArgumentException('Invalid job payload: missing required fields.');
        }

        // TODO M2: Record processing_started
        $this->logger->record('processing_started', [
            'requestId'  => $requestId,
            'campaignId' => $campaignId,
            'customerId' => $customerId,
        ]);

        // TODO P1: Use EmailHistory::isComplete() to return early for an already SENT or SKIPPED pair.
        if ($this->history->isComplete($campaignId, $customerId)) {
            $this->logger->record('duplicate_skipped', [
                'requestId'  => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
            ]);
            return;
        }

        // TODO P2: Look up the local customer using a prepared SELECT and the job's customerId.
        $stmt = $this->db->prepare(
            'SELECT customer_id, full_name, email, marketing_opt_in FROM customers WHERE customer_id = ?'
        );
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch();

        // TODO P3: Save SKIPPED and return if the customer is missing or not opted in. Include the reason.
        if (!$customer) {
            $reason = 'Customer record not found';
            $this->history->saveSkipped($campaignId, $customerId, $requestId, $promoCode, null, $subject, $reason);
            $this->logger->record('skipped', [
                'requestId'  => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'reason'     => $reason,
            ]);
            return;
        }

        if ((int) $customer['marketing_opt_in'] !== 1) {
            $reason = 'Customer has not opted in to marketing';
            $this->history->saveSkipped($campaignId, $customerId, $requestId, $promoCode, $customer['email'], $subject, $reason);
            $this->logger->record('skipped', [
                'requestId'  => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'reason'     => $reason,
            ]);
            return;
        }

        try {
            if ($this->delaySeconds > 0) {
                sleep($this->delaySeconds);
            }

            // TODO P4: Call the injected EmailSender and retain the returned MIME Message-ID.
            $mimeMessageId = $this->sender->send($customer, $job);
            $sentAt = gmdate('Y-m-d H:i:s');

            // TODO P5: Save SENT with recipient, provider, Message-ID, and UTC sent_at.
            $this->history->saveSent(
                $campaignId,
                $customerId,
                $requestId,
                $promoCode,
                (string) $customer['email'],
                $subject,
                'Mailtrap',
                $mimeMessageId,
                $sentAt
            );

            // TODO M2: Record sent only after the corresponding local outcome is saved.
            $this->logger->record('sent', [
                'requestId'  => $requestId,
                'campaignId' => $campaignId,
                'customerId' => $customerId,
                'messageId'  => $mimeMessageId,
            ]);
        } catch (Throwable $e) {
            $this->history->saveFailed(
                $campaignId,
                $customerId,
                $requestId,
                $promoCode,
                (string) ($customer['email'] ?? ''),
                $subject,
                $e->getMessage()
            );
            throw $e;
        }
    }
}