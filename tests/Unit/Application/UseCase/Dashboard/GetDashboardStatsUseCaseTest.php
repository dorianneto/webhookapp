<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Dashboard;

use App\Application\Port\DashboardStatsRepositoryPort;
use App\Application\UseCase\Dashboard\GetDashboardStatsUseCase;
use App\Application\Value\DashboardStats;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GetDashboardStatsUseCaseTest extends TestCase
{
    private DashboardStatsRepositoryPort&MockObject $repository;
    private GetDashboardStatsUseCase $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DashboardStatsRepositoryPort::class);
        $this->useCase    = new GetDashboardStatsUseCase($this->repository, new NullLogger());
    }

    public function testExecuteReturnsDashboardStats(): void
    {
        $stats = new DashboardStats(
            totalSources:        3,
            totalEndpoints:      7,
            totalEventsReceived: 100,
            deliveredEventsCount:90,
            pendingEventsCount:  5,
            failedEventsCount:   5,
            lastEventReceivedAt: new \DateTimeImmutable('2026-05-02T10:00:00+00:00'),
            quotaUsed:           100,
            quotaLimit:          10000,
        );

        $this->repository
            ->expects($this->once())
            ->method('getForUser')
            ->with('user-id')
            ->willReturn($stats);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame($stats, $result);
    }

    public function testExecuteWithNullLastEventReceivedAt(): void
    {
        $stats = new DashboardStats(
            totalSources:        0,
            totalEndpoints:      0,
            totalEventsReceived: 0,
            deliveredEventsCount:0,
            pendingEventsCount:  0,
            failedEventsCount:   0,
            lastEventReceivedAt: null,
            quotaUsed:           0,
            quotaLimit:          10000,
        );

        $this->repository
            ->expects($this->once())
            ->method('getForUser')
            ->willReturn($stats);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertNull($result->lastEventReceivedAt);
    }

    public function testExecuteWithZeroQuotaLimit(): void
    {
        $stats = new DashboardStats(
            totalSources:        1,
            totalEndpoints:      2,
            totalEventsReceived: 10,
            deliveredEventsCount:10,
            pendingEventsCount:  0,
            failedEventsCount:   0,
            lastEventReceivedAt: null,
            quotaUsed:           10,
            quotaLimit:          0,
        );

        $this->repository
            ->expects($this->once())
            ->method('getForUser')
            ->willReturn($stats);

        $result = $this->useCase->execute('request-id', 'user-id');

        $this->assertSame(0, $result->quotaLimit);
    }
}
