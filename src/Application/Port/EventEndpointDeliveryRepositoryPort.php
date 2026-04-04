<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\EventEndpointDelivery;
use App\Domain\EventStatus;

interface EventEndpointDeliveryRepositoryPort
{
    public function save(EventEndpointDelivery $delivery): void;

    public function findByEventAndEndpoint(string $eventId, string $endpointId): ?EventEndpointDelivery;

    public function updateStatus(string $id, EventStatus $status): void;

    /** @return EventEndpointDelivery[] */
    public function findAllByEvent(string $eventId): array;
}
