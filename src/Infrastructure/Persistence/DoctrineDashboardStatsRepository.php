<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Port\DashboardStatsRepositoryPort;
use App\Application\Port\PlanRepositoryPort;
use App\Application\Port\RequestUsageRepositoryPort;
use App\Application\Value\DashboardStats;
use Doctrine\DBAL\Connection;

final class DoctrineDashboardStatsRepository implements DashboardStatsRepositoryPort
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestUsageRepositoryPort $requestUsageRepository,
        private readonly PlanRepositoryPort $planRepository,
    ) {}

    public function getForUser(string $userId): DashboardStats
    {
        $row = $this->connection->fetchAssociative(
            'SELECT
                COUNT(DISTINCT s.id)                                         AS total_sources,
                COUNT(DISTINCT ep.id)                                        AS total_endpoints,
                COUNT(DISTINCT e.id)                                         AS total_events,
                COUNT(DISTINCT e.id) FILTER (WHERE e.status = \'delivered\') AS delivered_events,
                COUNT(DISTINCT e.id) FILTER (WHERE e.status = \'pending\')   AS pending_events,
                COUNT(DISTINCT e.id) FILTER (WHERE e.status = \'failed\')    AS failed_events,
                MAX(e.received_at)                                           AS last_event_received_at
             FROM sources s
             LEFT JOIN endpoints ep ON ep.source_id = s.id
             LEFT JOIN events e     ON e.source_id  = s.id
             WHERE s.user_id = :userId',
            ['userId' => $userId],
        );

        $quotaUsed  = $this->requestUsageRepository->sumRolling30Days($userId);
        $plan       = $this->planRepository->findByUserId($userId);
        $quotaLimit = $plan?->getMonthlyRequestLimit() ?? 0;

        return new DashboardStats(
            totalSources:        (int) ($row['total_sources'] ?? 0),
            totalEndpoints:      (int) ($row['total_endpoints'] ?? 0),
            totalEventsReceived: (int) ($row['total_events'] ?? 0),
            deliveredEventsCount:(int) ($row['delivered_events'] ?? 0),
            pendingEventsCount:  (int) ($row['pending_events'] ?? 0),
            failedEventsCount:   (int) ($row['failed_events'] ?? 0),
            lastEventReceivedAt: $row['last_event_received_at'] !== null
                ? new \DateTimeImmutable((string) $row['last_event_received_at'])
                : null,
            quotaUsed:           $quotaUsed,
            quotaLimit:          $quotaLimit,
        );
    }
}
