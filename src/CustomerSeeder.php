<?php

declare(strict_types=1);

namespace Lab;

use Faker\Generator;
use PDO;
use Throwable;

final class CustomerSeeder
{
    public function __construct(
        private PDO $db,
        private Generator $faker
    ) {
    }

    public function seed(): int
    {
        $sql = <<<SQL
            INSERT IGNORE INTO customers (
                customer_id,
                full_name,
                email,
                marketing_opt_in,
                created_at
            ) VALUES (
                :customer_id,
                :full_name,
                :email,
                :marketing_opt_in,
                :created_at
            )
        SQL;

        $stmt = $this->db->prepare($sql);
        $createdAt = gmdate('Y-m-d H:i:s');
        $inserted = 0;

        $this->db->beginTransaction();

        try {
            for ($i = 1; $i <= 10000; $i++) {
                $customerId = sprintf('CUST-%05d', $i);
                $email = sprintf('customer%05d@example.com', $i);

                // TODO S1: Generate a name using the injected Faker object.
                $name = $this->faker->name();

                // TODO S2: Assign consent 1 for customers 1-8,000 and 0 for customers 8,001-10,000.
                $consent = ($i <= 8000) ? 1 : 0;

                $stmt->execute([
                    ':customer_id'      => $customerId,
                    ':full_name'        => $name,
                    ':email'            => $email,
                    ':marketing_opt_in' => $consent,
                    ':created_at'       => $createdAt,
                ]);

                $inserted += $stmt->rowCount();
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $inserted;
    }
}