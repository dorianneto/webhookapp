<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Event;
use App\Domain\EventStatus;

interface EventRepositoryPort
{
    public function save(Event $event): void;

    public function findById(string $id): ?Event;

    /** @return Event[] */
    public function findRecentBySource(string $sourceId, int $limit): array;

    public function updateStatus(string $id, EventStatus $status): void;

    public function deleteByEndpointId(string $endpointId): void;
}
