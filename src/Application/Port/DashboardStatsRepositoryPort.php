<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Value\DashboardStats;

interface DashboardStatsRepositoryPort
{
    public function getForUser(string $userId): DashboardStats;
}
