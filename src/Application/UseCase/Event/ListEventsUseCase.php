<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Port\EventRepositoryPort;
use App\Domain\Event;

final class ListEventsUseCase
{
    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
    ) {}

    /** @return Event[] */
    public function execute(string $sourceId): array
    {
        return $this->eventRepository->findRecentBySource($sourceId, 100);
    }
}
