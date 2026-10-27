<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;
use Throwable;

final class CampaignPreparer
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function prepare(
        string $campaignId,
        string $promoCode,
        string $subject = 'Laboratory promotion',
        ?string $body = null
    ): int {
        $campaignId = trim($campaignId);
        $promoCode = trim($promoCode);

        if ($campaignId === '' || $promoCode === '') {
            throw new RuntimeException('Campaign ID and promo code are required.');
        }

        // TODO C3: Check existing outbox rows for the campaign before selecting.
        // If 50 already exist, keep the original selection unchanged.
        // If an unexpected partial selection exists, report the inconsistency.
        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = ?'
        );
        $countStmt->execute([$campaignId]);
        $existingCount = (int) $countStmt->fetchColumn();

        if ($existingCount === 50) {
            return 0;
        }

        if ($existingCount !== 0) {
            throw new RuntimeException(
                "Inconsistent campaign state for {$campaignId}: expected 0 or 50 outbox rows, found {$existingCount}."
            );
        }

        // TODO C2: Select exactly 50 distinct customers at random from those with marketing_opt_in = 1.
        // Sampling without replacement is guaranteed because customer_id is the primary key on customers.
        $selectSql = <<<SQL
            SELECT customer_id
            FROM customers
            WHERE marketing_opt_in = 1
            ORDER BY RAND()
            LIMIT 50
        SQL;
        $selectedCustomers = $this->db->query($selectSql)->fetchAll(PDO::FETCH_COLUMN);

        if (count($selectedCustomers) !== 50) {
            throw new RuntimeException(
                'Expected 50 eligible customers with marketing_opt_in = 1, found ' . count($selectedCustomers)
            );
        }

        $emailBody = $body ?? "You have been selected to receive our promotional offer. Use promo code: {$promoCode}";

        $insertSql = <<<SQL
            INSERT INTO marketing_outbox (
                request_id,
                campaign_id,
                customer_id,
                promo_code,
                subject,
                body,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        SQL;
        $insertStmt = $this->db->prepare($insertSql);

        $this->db->beginTransaction();

        try {
            $inserted = 0;

            foreach ($selectedCustomers as $customerId) {
                $requestId = bin2hex(random_bytes(16));
                $createdAt = gmdate('Y-m-d H:i:s');

                // TODO C1: Execute the prepared insert with seven values in order:
                // request ID, campaign ID, customer ID, promo code, subject, body, and creation time.
                $insertStmt->execute([
                    $requestId,
                    $campaignId,
                    (string) $customerId,
                    $promoCode,
                    $subject,
                    $emailBody,
                    $createdAt,
                ]);

                $inserted += $insertStmt->rowCount();
            }

            $this->db->commit();
            return $inserted;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}