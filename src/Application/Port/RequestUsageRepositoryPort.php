<?php

declare(strict_types=1);

namespace App\Application\Port;

interface RequestUsageRepositoryPort
{
    public function sumRolling30Days(string $userId): int;

    public function incrementToday(string $userId): void;

    public function deleteOlderThan(\DateTimeImmutable $before): int;
}
