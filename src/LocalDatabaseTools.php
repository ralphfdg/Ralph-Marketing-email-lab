<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;

final class LocalDatabaseTools
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function initializeSchema(string $schemaPath): void
    {
        if (!is_file($schemaPath)) {
            throw new RuntimeException("Schema file not found: {$schemaPath}");
        }

        $sql = file_get_contents($schemaPath);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException("Schema file is empty: {$schemaPath}");
        }

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            static fn(string $stmt): bool => $stmt !== ''
        );

        foreach ($statements as $statement) {
            $this->db->exec($statement);
        }
    }

    public function displaySummary(): void
    {
        echo "=== Local Database Summary ===" . PHP_EOL;

        $totalCustomers = (int) $this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        echo "Total customers: {$totalCustomers}" . PHP_EOL;

        $optInRows = $this->db->query(
            'SELECT marketing_opt_in, COUNT(*) AS total FROM customers GROUP BY marketing_opt_in ORDER BY marketing_opt_in DESC'
        )->fetchAll();
        foreach ($optInRows as $row) {
            $label = ((int) $row['marketing_opt_in'] === 1) ? 'Opted-In (1)' : 'Opted-Out (0)';
            echo "  - {$label}: {$row['total']}" . PHP_EOL;
        }

        echo PHP_EOL . "Outbox Counts by Status:" . PHP_EOL;
        $outboxRows = $this->db->query(
            'SELECT campaign_id, status, COUNT(*) AS total FROM marketing_outbox GROUP BY campaign_id, status ORDER BY campaign_id, status'
        )->fetchAll();
        if (empty($outboxRows)) {
            echo "  (empty)" . PHP_EOL;
        } else {
            foreach ($outboxRows as $row) {
                echo "  - [{$row['campaign_id']}] {$row['status']}: {$row['total']}" . PHP_EOL;
            }
        }

        echo PHP_EOL . "Sent Marketing Emails (History) Counts by Status:" . PHP_EOL;
        $historyRows = $this->db->query(
            'SELECT campaign_id, status, COUNT(*) AS total FROM sent_marketing_emails GROUP BY campaign_id, status ORDER BY campaign_id, status'
        )->fetchAll();
        if (empty($historyRows)) {
            echo "  (empty)" . PHP_EOL;
        } else {
            foreach ($historyRows as $row) {
                echo "  - [{$row['campaign_id']}] {$row['status']}: {$row['total']}" . PHP_EOL;
            }
        }
    }
}