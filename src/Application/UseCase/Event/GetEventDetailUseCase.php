<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Port\SourceRepositoryPort;
use App\Application\Value\EndpointDeliveryDetail;
use App\Application\Value\EventDetail;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;

#[WithMonologChannel('hookyard')]
final class GetEventDetailUseCase
{
    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
        private readonly EventEndpointDeliveryRepositoryPort $deliveryRepository,
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly DeliveryAttemptRepositoryPort $attemptRepository,
        private readonly SourceRepositoryPort $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(string $requestId, string $eventId, string $userId): ?EventDetail
    {
        $this->logger->info('Get event detail attempt', [
            'request_id' => $requestId,
            'event_id'   => $eventId,
        ]);

        $event = $this->eventRepository->findById($eventId);

        if ($event === null) {
            $this->logger->info('Get event detail not found', [
                'request_id' => $requestId,
                'event_id'   => $eventId,
            ]);

            return null;
        }

        if ($this->sourceRepository->findById($event->getSourceId(), $userId) === null) {
            $this->logger->info('Get event detail not found', [
                'request_id' => $requestId,
                'event_id'   => $eventId,
            ]);

            return null;
        }

        $deliveries = $this->deliveryRepository->findAllByEvent($eventId);

        $deliveryDetails = array_map(function ($delivery) use ($eventId) {
            $endpoint = $this->endpointRepository->findById($delivery->getEndpointId());
            $attempts = $this->attemptRepository->findAllByEventAndEndpoint($eventId, $delivery->getEndpointId());

            return new EndpointDeliveryDetail($delivery, $endpoint, $attempts);
        }, $deliveries);

        $this->logger->info('Get event detail returned', [
            'request_id' => $requestId,
            'event_id'   => $eventId,
        ]);

        return new EventDetail($event, $deliveryDetails);
    }
}
