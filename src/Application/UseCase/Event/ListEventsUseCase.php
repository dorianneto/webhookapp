<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Domain\Event;
use App\Domain\Exception\SourceNotFoundException;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class ListEventsUseCase
{
    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /** @return Event[] */
    public function execute(string $requestId, string $sourceId, string $userId): array
    {
        $this->logger->info('List events attempt', [
            'request_id' => $requestId,
            'source_id'  => $sourceId,
        ]);

        if ($this->sourceRepository->findById($sourceId, $userId) === null) {
            $this->logger->info('List events source not found', [
                'request_id' => $requestId,
                'source_id'  => $sourceId,
            ]);

            throw new SourceNotFoundException('Source not found.');
        }

        $events = $this->eventRepository->findRecentBySource($sourceId, 100);

        $this->logger->info('List events returned', [
            'request_id' => $requestId,
            'source_id'  => $sourceId,
            'count'      => \count($events),
        ]);

        return $events;
    }
}
