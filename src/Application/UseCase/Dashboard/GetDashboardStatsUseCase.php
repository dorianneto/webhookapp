<?php

declare(strict_types=1);

namespace App\Application\UseCase\Dashboard;

use App\Application\Port\DashboardStatsRepositoryPort;
use App\Application\Value\DashboardStats;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class GetDashboardStatsUseCase
{
    public function __construct(
        private readonly DashboardStatsRepositoryPort $dashboardStatsRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $userId): DashboardStats
    {
        $this->logger->info('Get dashboard stats attempt', [
            'request_id' => $requestId,
        ]);

        $stats = $this->dashboardStatsRepository->getForUser($userId);

        $this->logger->info('Get dashboard stats returned', [
            'request_id'      => $requestId,
            'total_sources'   => $stats->totalSources,
            'total_endpoints' => $stats->totalEndpoints,
            'total_events'    => $stats->totalEventsReceived,
            'failed_events'   => $stats->failedEventsCount,
        ]);

        return $stats;
    }
}
