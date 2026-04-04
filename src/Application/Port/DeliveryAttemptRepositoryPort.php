<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\DeliveryAttempt;

interface DeliveryAttemptRepositoryPort
{
    public function save(DeliveryAttempt $attempt): void;

    public function countByEventAndEndpoint(string $eventId, string $endpointId): int;
}
