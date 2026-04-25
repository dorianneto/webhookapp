<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\RequestUsageRepositoryPort;
use Doctrine\DBAL\Connection;

final class DoctrineRequestUsageRepository implements RequestUsageRepositoryPort
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function sumRolling30Days(string $userId): int
    {
        $result = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(count), 0)
             FROM request_usage
             WHERE user_id = :userId
               AND bucket_date >= CURRENT_DATE - 29',
            ['userId' => $userId],
        );

        return (int) $result;
    }

    public function incrementToday(string $userId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO request_usage (user_id, bucket_date, count)
             VALUES (:userId, CURRENT_DATE, 1)
             ON CONFLICT (user_id, bucket_date) DO UPDATE SET count = request_usage.count + 1',
            ['userId' => $userId],
        );
    }
}
