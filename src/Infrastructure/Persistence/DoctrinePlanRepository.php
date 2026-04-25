<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\PlanRepositoryPort;
use App\Domain\Plan as DomainPlan;
use Doctrine\DBAL\Connection;

final class DoctrinePlanRepository implements PlanRepositoryPort
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function findByUserId(string $userId): ?DomainPlan
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.id, p.name, p.monthly_request_limit, p.created_at
             FROM plans p
             INNER JOIN users u ON u.plan_id = p.id
             WHERE u.id = :userId',
            ['userId' => $userId],
        );

        if ($row === false) {
            return null;
        }

        return new DomainPlan(
            (string) $row['id'],
            (string) $row['name'],
            (int) $row['monthly_request_limit'],
            new \DateTimeImmutable((string) $row['created_at']),
        );
    }
}
