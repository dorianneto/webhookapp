<?php

declare(strict_types=1);

namespace App\Application\UseCase\Event;

use App\Application\Port\DeliveryAttemptRepositoryPort;
use App\Application\Port\EndpointRepositoryPort;
use App\Application\Port\EventEndpointDeliveryRepositoryPort;
use App\Application\Port\EventRepositoryPort;
use App\Application\Value\EndpointDeliveryDetail;
use App\Application\Value\EventDetail;

final class GetEventDetailUseCase
{
    public function __construct(
        private readonly EventRepositoryPort $eventRepository,
        private readonly EventEndpointDeliveryRepositoryPort $deliveryRepository,
        private readonly EndpointRepositoryPort $endpointRepository,
        private readonly DeliveryAttemptRepositoryPort $attemptRepository,
    ) {}

    public function execute(string $eventId): ?EventDetail
    {
        $event = $this->eventRepository->findById($eventId);

        if ($event === null) {
            return null;
        }

        $deliveries = $this->deliveryRepository->findAllByEvent($eventId);

        $deliveryDetails = array_map(function ($delivery) use ($eventId) {
            $endpoint = $this->endpointRepository->findById($delivery->getEndpointId());
            $attempts = $this->attemptRepository->findAllByEventAndEndpoint($eventId, $delivery->getEndpointId());

            return new EndpointDeliveryDetail($delivery, $endpoint, $attempts);
        }, $deliveries);

        return new EventDetail($event, $deliveryDetails);
    }
}
