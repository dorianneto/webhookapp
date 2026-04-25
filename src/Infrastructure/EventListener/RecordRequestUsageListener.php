<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Application\Event\IngestCompletedEvent;
use App\Application\Port\RequestUsageRepositoryPort;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: IngestCompletedEvent::class)]
#[WithMonologChannel('hookyard')]
final class RecordRequestUsageListener
{
    public function __construct(
        private readonly RequestUsageRepositoryPort $usageRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(IngestCompletedEvent $event): void
    {
        $this->usageRepository->incrementToday($event->userId);

        $this->logger->debug('Request usage incremented', [
            'request_id' => $event->requestId,
            'user_id'    => $event->userId,
            'event_id'   => $event->eventId,
        ]);
    }
}
