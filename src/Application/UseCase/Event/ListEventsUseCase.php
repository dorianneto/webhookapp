<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Event;
use App\Domain\Exception\SourceNotFoundException;

final class ListEventsUseCase
{
    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
        private readonly SourceRepositoryPort $sourceRepository,
    ) {}

    /** @return Event[] */
    public function execute(string $requestId, string $sourceId, string $userId): array
    {
        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            throw new SourceNotFoundException('Source not found.');
        }

        return $this->eventRepository->findRecentBySource($sourceId, 100);
    }
}
