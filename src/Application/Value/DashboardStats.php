<?php

declare(strict_types=1);

namespace App\Application\Value;

final readonly class DashboardStats
{
    public function __construct(
        public int $totalSources,
        public int $totalEndpoints,
        public int $totalEventsReceived,
        public int $deliveredEventsCount,
        public int $pendingEventsCount,
        public int $failedEventsCount,
        public ?\DateTimeImmutable $lastEventReceivedAt,
        public int $quotaUsed,
        public int $quotaLimit,
    ) {}
}
